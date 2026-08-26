/**
 * E-Examination — Student Exam Core Logic
 */

const ExamApp = {
    sessionId: null,
    soalList: [],
    currentIndex: 0,
    remainingSeconds: 0,
    timerInterval: null,
    saveTimer: null,
    isSubmitting: false,
    violations: 0,
    isTabActive: true,
    mediaRecorder: null,
    audioChunks: [],
    recordingSoalId: null,

    init() {
        if (typeof EXAM_SESSION_ID === 'undefined' || !EXAM_SESSION_ID) {
            window.location.href = 'dashboard.php';
            return;
        }
        this.sessionId = EXAM_SESSION_ID;

        $('<style>').text(`
            .recording-active { background: #dc2626 !important; border-color: #dc2626 !important; color: white !important; }
            .pulse { animation: recordPulse 1s infinite alternate; }
            @keyframes recordPulse { from { opacity: 1; transform: scale(1); } to { opacity: 0.4; transform: scale(1.2); } }
        `).appendTo('head');

        this.setupAntiCheat();
        this.fetchSoal();
    },

    setupAntiCheat() {
        // ===== 1. MULTI-TAB PREVENTION via localStorage =====
        const lockKey = `exam_lock_${this.sessionId}`;
        const lockValue = Date.now().toString();
        
        // Check if another tab already has this exam open
        const existingLock = localStorage.getItem(lockKey);
        if (existingLock && (Date.now() - parseInt(existingLock)) < 5000) {
            document.body.innerHTML = `
                <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;height:100vh;background:#0f172a;color:white;text-align:center;font-family:Inter,sans-serif;">
                    <svg viewBox="0 0 24 24" width="64" height="64" fill="none" stroke="#ef4444" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                    <h2 style="margin-top:24px;color:#ef4444;">Ujian Sudah Dibuka di Tab Lain</h2>
                    <p style="color:#94a3b8;max-width:400px;">Anda tidak dapat membuka ujian di lebih dari satu tab atau jendela. Tutup halaman ini dan kembali ke tab ujian sebelumnya.</p>
                    <a href="dashboard.php" style="margin-top:24px;padding:12px 24px;background:#2563EB;color:white;border-radius:8px;text-decoration:none;font-weight:600;">Kembali ke Dashboard</a>
                </div>`;
            return false;
        }
        
        // Set lock and refresh it periodically
        localStorage.setItem(lockKey, lockValue);
        this._lockInterval = setInterval(() => {
            localStorage.setItem(lockKey, Date.now().toString());
        }, 2000);
        
        // Listen for lock changes from other tabs
        window.addEventListener('storage', (e) => {
            if (e.key === lockKey && e.newValue !== null && e.newValue !== lockValue) {
                // Another tab took the lock — this shouldn't happen normally
                this.reportCheating('multi_tab');
            }
        });

        // Release lock and send lock signal on page unload if not submitted
        window.addEventListener('beforeunload', () => {
            clearInterval(this._lockInterval);
            if (this._heartbeatInterval) clearInterval(this._heartbeatInterval);
            localStorage.removeItem(lockKey);

            if (!this.isSubmitting) {
                const payload = JSON.stringify({ reason: 'Keluar / reload dari halaman ujian' });
                if (navigator.sendBeacon) {
                    const blob = new Blob([payload], { type: 'application/json' });
                    navigator.sendBeacon('../api/pengerjaan.php?action=lock_student', blob);
                }
            }
        });

        // Heartbeat interval (every 10 seconds)
        this._heartbeatInterval = setInterval(() => {
            if (!this.isSubmitting && this.sessionId) {
                $.ajax({
                    url: '../api/pengerjaan.php?action=heartbeat',
                    method: 'POST',
                    data: JSON.stringify({ session_id: this.sessionId, remaining_seconds: this.remainingSeconds }),
                    contentType: 'application/json'
                });
            }
        }, 10000);

        // ===== 2. FULLSCREEN ENFORCEMENT =====
        document.addEventListener('fullscreenchange', () => {
            if (!document.fullscreenElement) {
                $('#fullscreenOverlay').css('display', 'flex');
                this.reportCheating('exit_fullscreen');
            } else {
                $('#fullscreenOverlay').hide();
            }
        });

        if (!document.fullscreenElement) {
            $('#fullscreenOverlay').css('display', 'flex');
        }

        // ===== 3. VISIBILITY CHANGE (Switch tab) =====
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                this.isTabActive = false;
                this.reportCheating('tab_switch');
            } else {
                this.isTabActive = true;
            }
        });

        // ===== 4. WINDOW BLUR (Unfocus / Alt-Tab) =====
        window.addEventListener('blur', () => {
            if (this.isTabActive) {
                this.isTabActive = false;
                this.reportCheating('window_blur');
            }
        });
        window.addEventListener('focus', () => {
            this.isTabActive = true;
        });

        // ===== 5. CONTEXT MENU BLOCK =====
        document.addEventListener('contextmenu', e => e.preventDefault());

        // ===== 6. COPY / PASTE / CUT EVENT BLOCK =====
        ['copy', 'cut', 'paste'].forEach(evt => {
            document.addEventListener(evt, e => {
                // Allow paste only inside answer input fields
                if (evt === 'paste' && (e.target.tagName === 'TEXTAREA' || e.target.tagName === 'INPUT')) {
                    return; // let students paste into answer fields
                }
                e.preventDefault();
            });
        });

        // ===== 7. KEYBOARD SHORTCUT BLOCK =====
        document.addEventListener('keydown', e => {
            const key = e.key.toLowerCase();

            // Block: F12, Ctrl+Shift+I/J/C (DevTools), Ctrl+C/V/U/S/P, Alt+Tab, PrintScreen
            if (key === 'f12' || key === 'printscreen' ||
               (e.ctrlKey && e.shiftKey && ['i', 'j', 'c'].includes(key)) ||
               (e.ctrlKey && ['c', 'v', 'u', 's', 'p'].includes(key)) ||
               (e.altKey && key === 'tab')) {
                e.preventDefault();
                e.stopPropagation();

                if (key === 'printscreen') {
                    // Blank clipboard to prevent screenshot capture
                    navigator.clipboard.writeText('').catch(() => {});
                    this.reportCheating('screenshot');
                }
                return false;
            }

            // Block: Windows Key, Meta Key
            if (e.metaKey) {
                e.preventDefault();
            }
        });

        // ===== 8. PRINT SCREEN CAPTURE BLOCK =====
        // When PrintScreen is pressed, overwrite clipboard with blank
        document.addEventListener('keyup', e => {
            if (e.key === 'PrintScreen') {
                navigator.clipboard.writeText('').catch(() => {});
                this.reportCheating('screenshot');
            }
        });

        // ===== 9. DEVTOOLS OPEN DETECTION =====
        // Detect via window outer-inner size difference (resize trick)
        this._devtoolsCheckInterval = setInterval(() => {
            const widthDiff = window.outerWidth - window.innerWidth;
            const heightDiff = window.outerHeight - window.innerHeight;
            
            // If difference exceeds threshold, DevTools is likely open (docked)
            if (widthDiff > 200 || heightDiff > 200) {
                if (!this._devtoolsWarned) {
                    this._devtoolsWarned = true;
                    this.reportCheating('devtools');
                }
            } else {
                this._devtoolsWarned = false;
            }
        }, 2000);

        // ===== 10. DRAG PREVENTION =====
        document.addEventListener('dragstart', e => e.preventDefault());

        // ===== 11. PRINT BLOCK =====
        window.addEventListener('beforeprint', e => {
            e.preventDefault();
            this.reportCheating('print_attempt');
        });
    },

    enterFullscreen() {
        const docElm = document.documentElement;
        if (docElm.requestFullscreen) {
            docElm.requestFullscreen();
        } else if (docElm.mozRequestFullScreen) {
            docElm.mozRequestFullScreen();
        } else if (docElm.webkitRequestFullScreen) {
            docElm.webkitRequestFullScreen();
        } else if (docElm.msRequestFullscreen) {
            docElm.msRequestFullscreen();
        }
    },

    reportCheating(type) {
        if (this.isSubmitting) return;
        
        // Show big red violation overlay immediately
        this.showViolationOverlay(type);

        $.ajax({
            url: '../api/pengerjaan.php?action=report_cheat',
            method: 'POST',
            data: JSON.stringify({ session_id: this.sessionId, type: type }),
            contentType: 'application/json',
            success: (r) => {
                if (r.success) {
                    this.violations = r.data.violations;
                    if (r.data.action === 'stop') {
                        // Fatal — exam terminated
                        $('#violationOverlay').remove();
                        EModal.alert(
                            'UJIAN DIHENTIKAN',
                            `<div style="text-align:center;">
                                <svg viewBox="0 0 24 24" width="64" height="64" fill="none" stroke="#ef4444" stroke-width="2" style="margin:0 auto 16px;">
                                    <circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>
                                </svg>
                                <p style="font-size:16px;color:#0f172a;font-weight:600;">Ujian Anda dihentikan karena melakukan <strong>${this.violations} pelanggaran</strong>.</p>
                                <p style="color:#64748b;">Silakan hubungi pengawas ujian.</p>
                            </div>`,
                            () => { window.location.href = 'dashboard.php'; }
                        );
                    } else {
                        // Update the overlay counter
                        this.updateViolationOverlay();
                    }
                }
            }
        });
    },

    showViolationOverlay(type) {
        const typeLabels = {
            'exit_fullscreen': 'Keluar dari mode layar penuh',
            'tab_switch': 'Berpindah tab / aplikasi',
            'window_blur': 'Meninggalkan jendela ujian',
            'multi_tab': 'Membuka ujian di tab lain',
            'screenshot': 'Mencoba mengambil screenshot',
            'devtools': 'Membuka Developer Tools',
            'print_attempt': 'Mencoba mencetak halaman',
            'copy': 'Mencoba menyalin konten'
        };
        const label = typeLabels[type] || type;

        // Remove existing overlay if any
        $('#violationOverlay').remove();

        const overlay = $(`
            <div id="violationOverlay" style="
                position:fixed; top:0; left:0; right:0; bottom:0;
                background:rgba(127,29,29,0.97); color:white;
                display:flex; flex-direction:column; align-items:center; justify-content:center;
                z-index:99999; text-align:center; font-family:Inter,sans-serif;
                animation: violationFadeIn 0.3s ease;
            ">
                <style>
                    @keyframes violationFadeIn { from { opacity:0; transform:scale(1.05); } to { opacity:1; transform:scale(1); } }
                    @keyframes violationPulse { 0%,100% { transform:scale(1); } 50% { transform:scale(1.1); } }
                </style>
                <div style="animation:violationPulse 1s infinite;">
                    <svg viewBox="0 0 24 24" width="80" height="80" fill="none" stroke="#fca5a5" stroke-width="1.5">
                        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                        <line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
                    </svg>
                </div>
                <h1 style="font-size:28px; margin:24px 0 8px; color:#fca5a5;">⚠ PELANGGARAN TERDETEKSI</h1>
                <p style="font-size:18px; color:#fecaca; margin-bottom:8px; font-weight:600;">${label}</p>
                <p id="violationCounter" style="
                    font-size:15px; color:#fca5a5; margin-bottom:32px;
                    background:rgba(0,0,0,0.3); padding:8px 20px; border-radius:8px;
                ">Pelanggaran ke-${this.violations + 1} dari 3 (maks)</p>
                <button onclick="$('#violationOverlay').fadeOut(300, function(){ $(this).remove(); }); ExamApp.enterFullscreen();" style="
                    background:white; color:#991b1b; border:none; padding:14px 32px;
                    border-radius:10px; font-weight:700; font-size:16px; cursor:pointer;
                    box-shadow:0 4px 14px rgba(0,0,0,0.3);
                ">Kembali ke Ujian</button>
            </div>
        `);

        $('body').append(overlay);
    },

    updateViolationOverlay() {
        $('#violationCounter').text(`Pelanggaran ke-${this.violations} dari 3 (maks)`);
    },

    fetchSoal() {
        $.ajax({
            url: `../api/pengerjaan.php?action=get_soal&session_id=${this.sessionId}`,
            method: 'GET',
            success: (r) => {
                if (r.success) {
                    this.soalList = r.data.soal_list;
                    this.remainingSeconds = r.data.remaining_seconds;
                    $('#uiUjianJudul').text(r.data.ujian_judul);
                    
                    this.startTimer();
                    this.buildNavGrid();
                    
                    $('#uiLoading').hide();
                    $('#uiQuestionContainer, #uiActionBar').show();
                    
                    this.renderSoal(0);
                } else {
                    EModal.alert('Error', r.message, () => window.location.href='dashboard.php');
                }
            },
            error: (xhr) => {
                let msg = 'Gagal memuat soal';
                try { msg = xhr.responseJSON.message || msg; } catch(e){}
                EModal.alert('Error', msg, () => window.location.href='dashboard.php');
            }
        });
    },

    startTimer() {
        if (this.timerInterval) clearInterval(this.timerInterval);
        this.updateTimerUI();
        
        this.timerInterval = setInterval(() => {
            this.remainingSeconds--;
            if (this.remainingSeconds <= 0) {
                clearInterval(this.timerInterval);
                this.remainingSeconds = 0;
                this.updateTimerUI();
                this.autoSubmit();
            } else {
                this.updateTimerUI();
            }
        }, 1000);
    },

    updateTimerUI() {
        let s = this.remainingSeconds;
        let h = Math.floor(s / 3600);
        let m = Math.floor((s % 3600) / 60);
        let sc = s % 60;
        
        let formatted = `${h.toString().padStart(2, '0')}:${m.toString().padStart(2, '0')}:${sc.toString().padStart(2, '0')}`;
        $('#uiTimer').text(formatted);

        // Warning color if < 5 minutes
        if (s < 300) {
            $('#uiTimer').css({'background': '#b91c1c', 'color': 'white'});
        }
    },

    buildNavGrid() {
        const $grid = $('#uiNavGrid');
        $grid.empty();
        
        this.soalList.forEach((s, i) => {
            let classes = ['nav-btn'];
            if (this.hasAnswer(s.jawaban)) classes.push('answered');
            if (s.ragu_ragu) classes.push('doubt');
            
            $grid.append(`<button class="${classes.join(' ')}" id="navBtn_${i}" onclick="ExamApp.renderSoal(${i})">${i + 1}</button>`);
        });
    },

    updateNavGridStatus(index) {
        const s = this.soalList[index];
        const $btn = $(`#navBtn_${index}`);
        
        $btn.removeClass('answered doubt active');
        if (this.hasAnswer(s.jawaban)) $btn.addClass('answered');
        if (s.ragu_ragu) $btn.addClass('doubt');
        if (this.currentIndex === index) $btn.addClass('active');
    },

    hasAnswer(ans) {
        if (ans === null || ans === undefined || ans === '') return false;
        if (Array.isArray(ans) && ans.length === 0) return false;
        if (typeof ans === 'string') {
            try {
                let parsed = JSON.parse(ans);
                if (Array.isArray(parsed) && parsed.length === 0) return false;
            } catch(e) {}
        }
        return true;
    },

    renderSoal(index) {
        // Save current answer before switching (handled instantly on input, but good to ensure state)
        if (index < 0 || index >= this.soalList.length) return;
        
        // Remove active class from old
        $(`#navBtn_${this.currentIndex}`).removeClass('active');
        
        this.currentIndex = index;
        const currentData = this.soalList[index];
        const s = currentData.soal;

        // Update Nav
        $(`#navBtn_${this.currentIndex}`).addClass('active');
        
        // Header
        $('#uiSoalNo').text(`Soal No. ${currentData.urutan}`);
        
        let tNames = {
            'pilihan_satu': 'Pilihan Ganda',
            'pilihan_banyak': 'Pilihan Ganda Kompleks',
            'benar_salah': 'Benar / Salah',
            'menjodohkan': 'Menjodohkan',
            'jawaban_singkat': 'Jawaban Singkat',
            'essai': 'Esai'
        };
        $('#uiSoalTipe').text(tNames[s.tipe_soal] || 'Soal');

        // Doubt Button status
        if (currentData.ragu_ragu) {
            $('#btnDoubt').addClass('active');
            $('#cbDoubt').prop('checked', true);
        } else {
            $('#btnDoubt').removeClass('active');
            $('#cbDoubt').prop('checked', false);
        }

        // Action bar buttons
        $('#btnPrev').prop('disabled', index === 0).css('opacity', index === 0 ? '0.5' : '1');
        $('#btnNext').prop('disabled', index === this.soalList.length - 1).css('opacity', index === this.soalList.length - 1 ? '0.5' : '1');

        // Media
        this.audioPlayCount = this.audioPlayCount || {};
        let mediaHtml = '';
        if (s.gambar) mediaHtml += `<img src="../../uploads/exam/${s.gambar}" style="max-width:100%; border-radius:8px; margin-bottom:12px;">`;
        if (s.audio) {
            const playedCount = this.audioPlayCount[s.id] || 0;
            if (playedCount >= 2) {
                mediaHtml += `<div style="background:#fee2e2;color:#b91c1c;padding:12px;border-radius:8px;font-weight:600;text-align:center;margin-bottom:12px;">🔊 Audio listening telah diputar 2x (batas maksimal tercapai)</div>`;
            } else {
                mediaHtml += `
                    <div style="margin-bottom:12px;">
                        <audio id="audio_player_${s.id}" controls controlsList="nodownload noplaybackrate" style="width:100%;">
                            <source src="../../uploads/exam/${s.audio}" type="audio/mpeg">
                        </audio>
                        <div style="font-size:12px;color:#64748b;margin-top:4px;text-align:right;">Sisa pemutaran: <strong id="audio_remaining_${s.id}">${2 - playedCount}</strong> kali</div>
                    </div>
                `;
            }
        }
        $('#uiSoalMedia').html(mediaHtml);

        // Hook audio event
        if (s.audio && (this.audioPlayCount[s.id] || 0) < 2) {
            setTimeout(() => {
                const player = document.getElementById(`audio_player_${s.id}`);
                if (player) {
                    player.addEventListener('ended', () => {
                        this.audioPlayCount[s.id] = (this.audioPlayCount[s.id] || 0) + 1;
                        const newCount = this.audioPlayCount[s.id];
                        if (newCount >= 2) {
                            $(`#audio_player_${s.id}`).parent().html(`<div style="background:#fee2e2;color:#b91c1c;padding:12px;border-radius:8px;font-weight:600;text-align:center;margin-bottom:12px;">🔊 Audio listening telah diputar 2x (batas maksimal tercapai)</div>`);
                        } else {
                            $(`#audio_remaining_${s.id}`).text(2 - newCount);
                        }
                    });
                }
            }, 50);
        }

        // Text
        $('#uiSoalText').html(s.pertanyaan);

        // Options
        this.renderOptions(currentData);

        // Render Math equations if KaTeX is loaded
        if (window.renderMathInElement) {
            renderMathInElement(document.getElementById('uiQuestionContainer'), {
                delimiters: [
                    {left: "$$", right: "$$", display: true},
                    {left: "$", right: "$", display: false},
                    {left: "\\(", right: "\\)", display: false},
                    {left: "\\[", right: "\\]", display: true}
                ]
            });
        }
        
        // Scroll to top
        $('.exam-main').scrollTop(0);
    },

    renderOptions(data) {
        const s = data.soal;
        const $opts = $('#uiSoalOpsi');
        $opts.empty();

        let currentAns = data.jawaban;
        // Parse if JSON string (for arrays)
        if (typeof currentAns === 'string' && (currentAns.startsWith('[') || currentAns.startsWith('{'))) {
            try { currentAns = JSON.parse(currentAns); } catch(e){}
        }

        if (s.tipe_soal === 'pilihan_satu' || s.tipe_soal === 'benar_salah') {
            s.opsi.forEach((o, i) => {
                const isChecked = currentAns === o.label ? 'checked' : '';
                const selectedClass = isChecked ? 'selected' : '';
                const uid = `opt_${s.id}_${i}`;
                
                $opts.append(`
                    <label class="option-item ${selectedClass}" for="${uid}">
                        <input type="radio" name="ans_${s.id}" id="${uid}" value="${this.esc(o.label)}" ${isChecked} onchange="ExamApp.handleInput()">
                        <div class="option-label"><strong>${this.esc(o.label)}.</strong> ${o.teks}</div>
                    </label>
                `);
            });
        } 
        else if (s.tipe_soal === 'pilihan_banyak') {
            let ansArr = Array.isArray(currentAns) ? currentAns : [];
            s.opsi.forEach((o, i) => {
                const isChecked = ansArr.includes(o.label) ? 'checked' : '';
                const selectedClass = isChecked ? 'selected' : '';
                const uid = `opt_${s.id}_${i}`;
                
                $opts.append(`
                    <label class="option-item ${selectedClass}" for="${uid}">
                        <input type="checkbox" name="ans_${s.id}[]" id="${uid}" value="${this.esc(o.label)}" ${isChecked} onchange="ExamApp.handleInput()">
                        <div class="option-label"><strong>${this.esc(o.label)}.</strong> ${o.teks}</div>
                    </label>
                `);
            });
        }
        else if (s.tipe_soal === 'menjodohkan') {
            // Options contain "kiri" and "kanan" arrays
            let kiri = s.opsi.kiri || [];
            let kanan = s.opsi.kanan || [];
            let ansObj = (currentAns && typeof currentAns === 'object') ? currentAns : {};

            let html = '<div style="display:flex; flex-direction:column; gap:16px;">';
            kiri.forEach((kItem, i) => {
                let selHtml = `<select class="form-input" style="width:100%;max-width:300px;" name="ans_${s.id}_${i}" data-kiri="${this.esc(kItem)}" onchange="ExamApp.handleInput()">`;
                selHtml += `<option value="">-- Pilih Pasangan --</option>`;
                kanan.forEach(knItem => {
                    let sel = (ansObj[kItem] === knItem) ? 'selected' : '';
                    selHtml += `<option value="${this.esc(knItem)}" ${sel}>${this.esc(knItem)}</option>`;
                });
                selHtml += `</select>`;

                html += `
                    <div style="display:flex; gap:16px; align-items:center; background:#f8fafc; padding:12px; border-radius:8px; border:1px solid var(--border-color);">
                        <div style="flex:1; font-weight:500;">${this.esc(kItem)}</div>
                        <div style="flex:1;">${selHtml}</div>
                    </div>
                `;
            });
            html += '</div>';
            $opts.html(html);
        }
        else if (s.tipe_soal === 'jawaban_singkat') {
            const voiceHtml = `
                <div class="voice-recorder-wrapper" style="margin-top:16px;padding:16px;background:#f8fafc;border-radius:12px;border:1px solid #e2e8f0;">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
                        <span style="font-size:13px;font-weight:600;color:#334155;display:flex;align-items:center;gap:6px;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" y1="19" x2="12" y2="23"/></svg>
                            Jawaban Suara (Listening/Voice Answer)
                        </span>
                        <span id="voice_status_${s.id}" style="font-size:12px;color:#2563eb;font-weight:500;">
                            ${data.jawaban_voice ? 'Sudah terekam' : 'Ready'}
                        </span>
                    </div>
                    
                    <div style="display:flex;gap:12px;align-items:center;">
                        <button type="button" class="btn btn-outline" id="btn_record_${s.id}" onclick="ExamApp.toggleRecord(${s.id})" style="border-radius:24px;padding:8px 16px;font-size:13px;display:inline-flex;align-items:center;gap:6px;">
                            <span class="record-dot" style="width:10px;height:10px;background:currentColor;border-radius:50%;display:inline-block;"></span>
                            Rekam Suara
                        </button>
                        
                        <div id="voice_preview_${s.id}" style="flex:1;display:${data.jawaban_voice ? 'block' : 'none'};">
                            <audio id="audio_voice_preview_${s.id}" controls style="width:100%;height:36px;border-radius:18px;">
                                <source src="../../${data.jawaban_voice}" type="audio/webm">
                            </audio>
                        </div>
                    </div>
                </div>
            `;
            $opts.html(`
                <input type="text" class="input-short" id="ans_${s.id}" placeholder="Ketik jawaban singkat Anda..." value="${this.esc(currentAns || '')}" oninput="ExamApp.handleDebouncedInput()">
                ${voiceHtml}
            `);
        }
        else if (s.tipe_soal === 'essai') {
            const voiceHtml = `
                <div class="voice-recorder-wrapper" style="margin-top:16px;padding:16px;background:#f8fafc;border-radius:12px;border:1px solid #e2e8f0;">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
                        <span style="font-size:13px;font-weight:600;color:#334155;display:flex;align-items:center;gap:6px;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" y1="19" x2="12" y2="23"/></svg>
                            Jawaban Suara (Listening/Voice Answer)
                        </span>
                        <span id="voice_status_${s.id}" style="font-size:12px;color:#2563eb;font-weight:500;">
                            ${data.jawaban_voice ? 'Sudah terekam' : 'Ready'}
                        </span>
                    </div>
                    
                    <div style="display:flex;gap:12px;align-items:center;">
                        <button type="button" class="btn btn-outline" id="btn_record_${s.id}" onclick="ExamApp.toggleRecord(${s.id})" style="border-radius:24px;padding:8px 16px;font-size:13px;display:inline-flex;align-items:center;gap:6px;">
                            <span class="record-dot" style="width:10px;height:10px;background:currentColor;border-radius:50%;display:inline-block;"></span>
                            Rekam Suara
                        </button>
                        
                        <div id="voice_preview_${s.id}" style="flex:1;display:${data.jawaban_voice ? 'block' : 'none'};">
                            <audio id="audio_voice_preview_${s.id}" controls style="width:100%;height:36px;border-radius:18px;">
                                <source src="../../${data.jawaban_voice}" type="audio/webm">
                            </audio>
                        </div>
                    </div>
                </div>
            `;
            $opts.html(`
                <textarea class="input-essay" id="ans_${s.id}" placeholder="Ketik jawaban Anda dengan jelas dan lengkap...">${this.esc(currentAns || '')}</textarea>
                ${voiceHtml}
            `);
            $(`#ans_${s.id}`).on('input', () => this.handleDebouncedInput());
        }
    },

    handleInput() {
        this.saveCurrentAnswer();
    },

    handleDebouncedInput() {
        if (this.saveTimer) clearTimeout(this.saveTimer);
        this.saveTimer = setTimeout(() => {
            this.saveCurrentAnswer();
        }, 1000); // 1 second debounce for text typing
    },

    saveCurrentAnswer() {
        const currentData = this.soalList[this.currentIndex];
        const s = currentData.soal;
        let newAns = null;

        if (s.tipe_soal === 'pilihan_satu' || s.tipe_soal === 'benar_salah') {
            newAns = $(`input[name="ans_${s.id}"]:checked`).val() || null;
            // Update UI class
            $(`input[name="ans_${s.id}"]`).closest('.option-item').removeClass('selected');
            $(`input[name="ans_${s.id}"]:checked`).closest('.option-item').addClass('selected');
        } 
        else if (s.tipe_soal === 'pilihan_banyak') {
            newAns = [];
            $(`input[name="ans_${s.id}[]"]:checked`).each(function() {
                newAns.push($(this).val());
            });
            // Update UI class
            $(`input[name="ans_${s.id}[]"]`).closest('.option-item').removeClass('selected');
            $(`input[name="ans_${s.id}[]"]:checked`).closest('.option-item').addClass('selected');
        }
        else if (s.tipe_soal === 'menjodohkan') {
            newAns = {};
            $(`select[name^="ans_${s.id}_"]`).each(function() {
                let k = $(this).data('kiri');
                let v = $(this).val();
                if (v) newAns[k] = v;
            });
        }
        else if (s.tipe_soal === 'jawaban_singkat' || s.tipe_soal === 'essai') {
            newAns = $(`#ans_${s.id}`).val().trim();
        }

        currentData.jawaban = newAns;
        this.updateNavGridStatus(this.currentIndex);
        this.syncToServer();
    },

    toggleDoubt() {
        const currentData = this.soalList[this.currentIndex];
        currentData.ragu_ragu = !currentData.ragu_ragu;
        
        if (currentData.ragu_ragu) {
            $('#btnDoubt').addClass('active');
            $('#cbDoubt').prop('checked', true);
        } else {
            $('#btnDoubt').removeClass('active');
            $('#cbDoubt').prop('checked', false);
        }

        this.updateNavGridStatus(this.currentIndex);
        this.syncToServer();
    },

    syncToServer() {
        const currentData = this.soalList[this.currentIndex];
        const payload = {
            session_id: this.sessionId,
            jawaban_id: currentData.jawaban_id,
            jawaban: currentData.jawaban,
            ragu_ragu: currentData.ragu_ragu ? 1 : 0
        };

        $.ajax({
            url: '../api/pengerjaan.php?action=save_jawaban',
            method: 'POST',
            data: JSON.stringify(payload),
            contentType: 'application/json',
            // Silently fail, user doesn't need constant toasts
        });
    },

    prevSoal() {
        if (this.currentIndex > 0) this.renderSoal(this.currentIndex - 1);
    },

    nextSoal() {
        if (this.currentIndex < this.soalList.length - 1) this.renderSoal(this.currentIndex + 1);
    },

    finishExam() {
        // Validations
        let unanswered = 0;
        let doubts = 0;
        this.soalList.forEach(s => {
            if (!this.hasAnswer(s.jawaban)) unanswered++;
            if (s.ragu_ragu) doubts++;
        });

        let msg = 'Apakah Anda yakin ingin menyelesaikan ujian ini? Setelah selesai, Anda tidak bisa lagi mengubah jawaban.';
        if (unanswered > 0) msg += `<br><br><span style="color:#b91c1c;">Terdapat <strong>${unanswered} soal</strong> yang belum dijawab.</span>`;
        if (doubts > 0) msg += `<br><span style="color:#b91c1c;">Terdapat <strong>${doubts} soal</strong> yang masih ditandai Ragu-ragu.</span>`;

        EModal.confirm({
            title: 'Selesai Ujian',
            message: msg,
            type: 'primary',
            confirmText: 'Ya, Selesai',
            onConfirm: () => {
                this.doSubmit();
            }
        });
    },

    autoSubmit() {
        EModal.info({
            title: 'Waktu Habis',
            message: 'Waktu pengerjaan ujian telah habis. Jawaban Anda akan disimpan secara otomatis.',
            type: 'warning',
            onConfirm: () => {
                this.doSubmit();
            }
        });
    },

    doSubmit() {
        this.isSubmitting = true;
        const loader = EModal.loading('Menyimpan hasil ujian...');
        $.ajax({
            url: '../api/pengerjaan.php?action=submit',
            method: 'POST',
            data: JSON.stringify({ session_id: this.sessionId }),
            contentType: 'application/json',
            success: (r) => {
                EModal.close(loader);
                if (r.success) {
                    window.location.href = 'dashboard.php';
                } else {
                    EModal.alert('Gagal', r.message);
                    this.isSubmitting = false;
                }
            },
            error: () => {
                EModal.close(loader);
                EModal.alert('Error', 'Gagal terhubung ke server');
                this.isSubmitting = false;
            }
        });
    },

    esc(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    },

    toggleRecord(soalId) {
        const $btn = $(`#btn_record_${soalId}`);
        const $status = $(`#voice_status_${soalId}`);
        
        if (this.mediaRecorder && this.mediaRecorder.state === 'recording') {
            // Stop recording
            this.mediaRecorder.stop();
            $btn.html('<span class="record-dot" style="width:10px;height:10px;background:currentColor;border-radius:50%;display:inline-block;"></span> Rekam Suara').removeClass('recording-active');
            $status.text('Mengupload rekaman...');
        } else {
            // Start recording
            navigator.mediaDevices.getUserMedia({ audio: true }).then(stream => {
                this.audioChunks = [];
                this.recordingSoalId = soalId;
                this.mediaRecorder = new MediaRecorder(stream);
                
                this.mediaRecorder.ondataavailable = event => {
                    this.audioChunks.push(event.data);
                };
                
                this.mediaRecorder.onstop = () => {
                    const audioBlob = new Blob(this.audioChunks, { type: 'audio/webm' });
                    this.uploadVoiceBlob(soalId, audioBlob);
                    
                    // Stop tracks to release mic
                    stream.getTracks().forEach(track => track.stop());
                };
                
                this.mediaRecorder.start();
                $btn.html('<span class="record-dot pulse" style="width:10px;height:10px;background:red;border-radius:50%;display:inline-block;"></span> Hentikan').addClass('recording-active');
                $status.text('Sedang merekam...');
            }).catch(err => {
                EModal.alert('Mikrofon Gagal', 'Harap izinkan akses mikrofon untuk merekam jawaban suara.');
            });
        }
    },

    uploadVoiceBlob(soalId, blob) {
        const currentData = this.soalList[this.currentIndex];
        const formData = new FormData();
        formData.append('voice', blob, `voice_${currentData.jawaban_id}.webm`);
        formData.append('session_id', this.sessionId);
        formData.append('jawaban_id', currentData.jawaban_id);

        $.ajax({
            url: '../api/pengerjaan.php?action=upload_voice',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: (r) => {
                if (r.success) {
                    currentData.jawaban_voice = r.data.path;
                    $(`#voice_status_${soalId}`).text('Sudah terekam').css('color', '#059669');
                    
                    // Refresh preview audio player
                    $(`#voice_preview_${soalId}`).show();
                    const player = document.getElementById(`audio_voice_preview_${soalId}`);
                    player.src = `../../${r.data.path}`;
                    player.load();

                    // Update nav status
                    this.updateNavGridStatus(this.currentIndex);
                } else {
                    $(`#voice_status_${soalId}`).text('Gagal upload').css('color', '#dc2626');
                    EModal.alert('Gagal', r.message);
                }
            },
            error: () => {
                $(`#voice_status_${soalId}`).text('Gagal upload').css('color', '#dc2626');
                EModal.alert('Error', 'Gagal mengupload audio');
            }
        });
    }
};

$(document).ready(() => ExamApp.init());
