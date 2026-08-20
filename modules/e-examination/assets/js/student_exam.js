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

    init() {
        if (typeof EXAM_SESSION_ID === 'undefined' || !EXAM_SESSION_ID) {
            window.location.href = 'dashboard.php';
            return;
        }
        this.sessionId = EXAM_SESSION_ID;

        this.setupAntiCheat();
        this.fetchSoal();
    },

    setupAntiCheat() {
        // Enforce Fullscreen
        document.addEventListener('fullscreenchange', () => {
            if (!document.fullscreenElement) {
                $('#fullscreenOverlay').css('display', 'flex');
                this.reportCheating('exit_fullscreen');
            } else {
                $('#fullscreenOverlay').hide();
            }
        });

        // Check if already fullscreen
        if (!document.fullscreenElement) {
            $('#fullscreenOverlay').css('display', 'flex');
        }

        // Visibility Change (Switch tab)
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                this.isTabActive = false;
                this.reportCheating('tab_switch');
            } else {
                this.isTabActive = true;
            }
        });

        // Prevent Context Menu
        document.addEventListener('contextmenu', e => e.preventDefault());
        
        // Prevent Keyboard Shortcuts (Copy, Paste, F12, etc)
        document.addEventListener('keydown', e => {
            if (e.key === 'F12' || 
               (e.ctrlKey && e.shiftKey && e.key === 'I') || 
               (e.ctrlKey && e.key === 'c') ||
               (e.ctrlKey && e.key === 'v') ||
               (e.ctrlKey && e.key === 'u') ||
               (e.altKey && e.key === 'Tab')) {
                e.preventDefault();
            }
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
        if (this.isSubmitting) return; // Ignore if already submitting
        
        $.ajax({
            url: '../api/pengerjaan.php?action=report_cheat',
            method: 'POST',
            data: JSON.stringify({ session_id: this.sessionId, type: type }),
            contentType: 'application/json',
            success: (r) => {
                if (r.success) {
                    this.violations = r.data.violations;
                    if (r.data.action === 'stop') {
                        EModal.alert('Pelanggaran Fatal', 'Ujian Anda dihentikan karena terdeteksi melakukan pelanggaran lebih dari 3 kali. Silakan hubungi pengawas.', () => {
                            window.location.href = 'dashboard.php';
                        });
                    } else {
                        EModal.toast({type:'error', title:`Peringatan Pelanggaran (${this.violations}/3)`});
                    }
                }
            }
        });
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
        let mediaHtml = '';
        if (s.gambar) mediaHtml += `<img src="../../uploads/exam/${s.gambar}" style="max-width:100%; border-radius:8px; margin-bottom:12px;">`;
        if (s.audio) mediaHtml += `<audio controls controlsList="nodownload noplaybackrate" style="width:100%;"><source src="../../uploads/exam/${s.audio}" type="audio/mpeg"></audio>`;
        $('#uiSoalMedia').html(mediaHtml);

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
            $opts.html(`
                <input type="text" class="input-short" id="ans_${s.id}" placeholder="Ketik jawaban singkat Anda..." value="${this.esc(currentAns || '')}" oninput="ExamApp.handleDebouncedInput()">
            `);
        }
        else if (s.tipe_soal === 'essai') {
            $opts.html(`
                <textarea class="input-essay" id="ans_${s.id}" placeholder="Ketik jawaban Anda dengan jelas dan lengkap...">${this.esc(currentAns || '')}</textarea>
            `);
            // Essay uses debounced input
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
    }
};

$(document).ready(() => ExamApp.init());
