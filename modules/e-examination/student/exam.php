<?php
/**
 * E-Examination — Student Exam Interface
 */
require_once __DIR__ . '/../../api/config.php';

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
    
    <!-- KaTeX for Math Equations -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/katex.min.css">
    
    <style>
        :root {
            --bg-color: #f1f5f9;
            --text-main: #0f172a;
            --primary: #2563EB;
            --border-color: #e2e8f0;
        }
        body {
            background-color: var(--bg-color);
            color: var(--text-main);
            font-family: 'Inter', sans-serif;
            margin: 0; padding: 0;
            display: flex;
            flex-direction: column;
            height: 100vh;
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
            z-index: 10;
        }
        .exam-title {
            font-weight: 700;
            font-size: 18px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .exam-timer {
            font-family: monospace;
            font-size: 20px;
            font-weight: bold;
            color: #b91c1c;
            background: #fef2f2;
            padding: 6px 12px;
            border-radius: 8px;
            border: 1px solid #fecaca;
        }
        
        /* Main Layout */
        .exam-layout {
            display: flex;
            flex: 1;
            overflow: hidden;
        }
        
        /* Sidebar (Navigasi Soal) */
        .exam-sidebar {
            width: 320px;
            background: white;
            border-right: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            transition: transform 0.3s ease;
        }
        .sidebar-header {
            padding: 16px;
            font-weight: 600;
            border-bottom: 1px solid var(--border-color);
            background: #f8fafc;
        }
        .nav-grid {
            padding: 16px;
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 10px;
            overflow-y: auto;
            flex: 1;
        }
        .nav-btn {
            aspect-ratio: 1;
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
            transition: 0.2s;
            position: relative;
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

        /* Main Content (Soal) */
        .exam-main {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: var(--bg-color);
            overflow-y: auto;
            position: relative;
        }
        .question-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 32px;
            width: 100%;
            box-sizing: border-box;
            flex: 1;
        }
        .question-card {
            background: white;
            border-radius: 12px;
            padding: 32px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
            margin-bottom: 24px;
            font-size: 16px;
            line-height: 1.6;
        }
        .question-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--border-color);
            font-weight: 600;
            color: #64748b;
        }
        .question-text img {
            max-width: 100%;
            height: auto;
            border-radius: 8px;
            margin: 16px 0;
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
            gap: 16px;
            padding: 16px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
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
            margin-top: 4px;
            width: 18px;
            height: 18px;
            accent-color: #2563EB;
        }
        .option-label {
            flex: 1;
            user-select: none;
        }
        
        /* Action Bar */
        .action-bar {
            background: white;
            border-top: 1px solid var(--border-color);
            padding: 16px 32px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            bottom: 0;
            z-index: 5;
        }
        .btn-exam {
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 15px;
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
            background: rgba(15,23,42,0.95);
            color: white;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            text-align: center;
        }
        #fullscreenOverlay h2 { margin-bottom: 16px; }
        #fullscreenOverlay button {
            background: #2563EB; color: white; border: none; padding: 12px 24px;
            border-radius: 8px; font-weight: bold; font-size: 16px; cursor: pointer;
        }

        /* Utility classes */
        .hidden { display: none !important; }
        .flex { display: flex; }
        
        /* Input types for essay/short answer */
        .input-essay {
            width: 100%;
            padding: 16px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-family: inherit;
            font-size: 15px;
            min-height: 150px;
            resize: vertical;
        }
        .input-short {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 15px;
        }
    </style>
</head>
<body>

    <!-- Fullscreen Enforcement Overlay -->
    <div id="fullscreenOverlay">
        <h2>Ujian Membutuhkan Layar Penuh</h2>
        <p style="color:#94a3b8; margin-bottom:32px; max-width:500px;">
            Untuk alasan keamanan dan integritas ujian, Anda diwajibkan menggunakan mode layar penuh (Fullscreen).
            Menutup mode layar penuh dapat dianggap sebagai pelanggaran.
        </p>
        <button onclick="ExamApp.enterFullscreen()">Masuk Mode Layar Penuh</button>
    </div>

    <!-- Header -->
    <header class="exam-header">
        <div class="exam-title">
            <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="#2563EB" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            <span id="uiUjianJudul">Memuat Ujian...</span>
        </div>
        <div class="exam-timer" id="uiTimer">00:00:00</div>
    </header>

    <!-- Layout -->
    <div class="exam-layout">
        
        <!-- Sidebar Navigation -->
        <aside class="exam-sidebar" id="sidebar">
            <div class="sidebar-header">
                Daftar Soal
            </div>
            <div class="nav-grid" id="uiNavGrid">
                <!-- Buttons injected by JS -->
            </div>
            <div style="padding:16px; border-top:1px solid #e2e8f0; text-align:center;">
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
            <div id="uiLoading" style="display:flex; flex-direction:column; align-items:center; justify-content:center; flex:1; color:#64748b;">
                <svg viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="currentColor" stroke-width="2" style="animation:spin 1s linear infinite;"><line x1="12" y1="2" x2="12" y2="6"/><line x1="12" y1="18" x2="12" y2="22"/><line x1="4.93" y1="4.93" x2="7.76" y2="7.76"/><line x1="16.24" y1="16.24" x2="19.07" y2="19.07"/><line x1="2" y1="12" x2="6" y2="12"/><line x1="18" y1="12" x2="22" y2="12"/><line x1="4.93" y1="19.07" x2="7.76" y2="16.24"/><line x1="16.24" y1="7.76" x2="19.07" y2="4.93"/></svg>
                <p style="margin-top:16px;">Memuat soal ujian...</p>
                <style>@keyframes spin { 100% { transform:rotate(360deg); } }</style>
            </div>

            <!-- Action Bar -->
            <div class="action-bar" id="uiActionBar" style="display:none;">
                <button class="btn-exam btn-prev" id="btnPrev" onclick="ExamApp.prevSoal()">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
                    Sebelumnya
                </button>
                
                <button class="btn-exam btn-doubt" id="btnDoubt" onclick="ExamApp.toggleDoubt()">
                    <input type="checkbox" id="cbDoubt" style="pointer-events:none;">
                    Ragu-ragu
                </button>
                
                <button class="btn-exam btn-next" id="btnNext" onclick="ExamApp.nextSoal()">
                    Selanjutnya
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
    
    <!-- Core App Logic -->
    <script>
        const EXAM_SESSION_ID = <?php echo $session_id; ?>;
    </script>
    <script src="../assets/js/student_exam.js"></script>
</body>
</html>
