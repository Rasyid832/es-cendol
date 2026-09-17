<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/../../config/db.php'; // $pdo (PDO)

// Proteksi: wajib login sebagai student
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'student') {
    header("Location: login.php");
    exit();
}

// Dapatkan Data User dari Session
$user_id = $_SESSION['user_id'] ?? null;
$user_email = $_SESSION['email'] ?? 'SISWA';
$user_name = $_SESSION['name'] ?? 'User Coding';
$identity_number = $_SESSION['identity_number'] ?? 'DEV-001';

// Ambil project/room_id dari parameter URL (opsional)
$room_id = isset($_GET['room_id']) ? intval($_GET['room_id']) : ($_SESSION['room_id'] ?? 1);

// Default Fallback
$project_title = "Workspace Project";
$duration_minutes = 90; 
$project_description = "Tulis dan kembangkan kode program Anda di sini.";
$question_text    = "// Tulis solusi koding Anda di sini\nprint('Hello World');";

// === GUARD: wajib sudah "join" dulu lewat dashboard, dan sesi harus masih 'ongoing' ===
try {
    $stmtSession = $pdo->prepare("
        SELECT * FROM sessions
        WHERE room_id = :room_id AND student_id = :student_id
        ORDER BY id DESC LIMIT 1
    ");
    $stmtSession->execute(['room_id' => $room_id, 'student_id' => $user_id]);
    $exam_session = $stmtSession->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('[LEMBAR GUARD ERROR] ' . $e->getMessage());
    $exam_session = false;
}

if (!$exam_session) {
    $_SESSION['join_error'] = "Anda belum bergabung ke room ini. Masukkan ID Room dari dashboard.";
    header("Location: ../student/dashboard.php");
    exit();
}

if ($exam_session['status'] === 'forfeited') {
    $_SESSION['join_error'] = "Ujian ini sudah gugur karena Anda pernah keluar dari halaman ujian / melanggar aturan.";
    header("Location: ../student/dashboard.php");
    exit();
}

if ($exam_session['status'] === 'completed') {
    $_SESSION['join_error'] = "Anda sudah menyelesaikan/mengumpulkan ujian ini sebelumnya.";
    header("Location: ../student/dashboard.php");
    exit();
}

// Token CSRF untuk komunikasi ke controller pelanggaran & finish exam
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

try {
    $stmtRoom = $pdo->prepare("SELECT * FROM rooms WHERE id = :id LIMIT 1");
    $stmtRoom->execute(['id' => $room_id]);
    $room = $stmtRoom->fetch(PDO::FETCH_ASSOC);

    if ($room) {
        $project_title       = $room['subject_name'] ?? $room['title'] ?? $room['name'] ?? "Workspace Project";
        $duration_minutes    = isset($room['duration']) ? intval($room['duration']) : 90;
        $project_description = $room['description'] ?? "Silakan ikuti instruksi project dengan teliti.";
        
        if (!empty($room['question_text'])) {
            $question_text = $room['question_text'];
        }
    }
} catch (PDOException $e) {
    // Abaikan atau log error jika diperlukan
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monaco Code Editor - <?= htmlspecialchars($project_title) ?></title>
    
    <!-- Google Fonts & Tailwind -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- 1. Muat TensorFlow & COCO-SSD v2.2.2 (Stabil) -->
    <script src="https://cdn.jsdelivr.net/npm/@tensorflow/tfjs@3.18.0/dist/tf.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@tensorflow-models/coco-ssd"></script>

    <!-- 2. Gunakan unpkg untuk Monaco Editor Loader -->
    <script src="https://unpkg.com/monaco-editor@0.33.0/min/vs/loader.js"></script>

    <!-- 3. CSS Monaco Editor Resmi dari unpkg (Mengatasi error codicon.ttf lokal) -->
    <link rel="stylesheet" data-name="vs/editor/editor.main" href="https://unpkg.com/monaco-editor@0.33.0/min/vs/editor/editor.main.css">
    
    <style>
        body { font-family: 'Inter', sans-serif; }
        .font-mono { font-family: 'JetBrains Mono', monospace; }
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: #1e1e1e; }
        ::-webkit-scrollbar-thumb { background: #424242; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #4f4f4f; }
    </style>
</head>
<body class="bg-[#1e1e1e] text-[#cccccc] h-screen flex flex-col overflow-hidden select-none">

    <!-- OVERLAY: Wajib klik dulu supaya browser mengizinkan Fullscreen API -->
    <div id="start-overlay" class="fixed inset-0 z-50 bg-black/85 flex items-center justify-center px-6">
        <div class="bg-[#2d2d2d] border border-[#444] rounded-2xl shadow-2xl p-8 max-w-md w-full text-center text-gray-200">
            <h1 class="text-xl font-bold text-white mb-2">Siap Memulai Ujian?</h1>
            <p class="text-sm text-gray-400 mb-6 leading-relaxed">
                Ujian berjalan dengan mode perlindungan AI Proctoring. (Mode Debugging Aktif: Klik kanan & Inspect Element diizinkan).
            </p>
            <button id="start-btn" class="bg-red-600 hover:bg-red-700 active:scale-95 text-white font-semibold px-6 py-3 rounded-xl w-full transition-all shadow-lg">
                Mulai Ujian (Fullscreen)
            </button>
        </div>
    </div>

    <!-- WARNING BANNER: muncul saat pelanggaran terdeteksi -->
    <div id="violation-banner" class="hidden fixed top-0 left-0 right-0 z-40 bg-red-600 text-white text-sm font-semibold text-center py-2.5 px-4 shadow-md">
        <span id="violation-text">Pelanggaran terdeteksi.</span>
        <span id="violation-count-wrap"> (Pelanggaran ke-<span id="violation-count">0</span> dari 3)</span>
    </div>

    <!-- HIDDEN WEBCAM ELEMENT UNTUK PROCTORING AI -->
    <video id="proctoring-video" autoplay playsinline muted style="display:none;"></video>

    <!-- VS CODE WINDOW TITLE BAR -->
    <div class="bg-[#3c3c3c] text-[#cccccc] h-14 px-6 flex justify-between items-center text-sm border-b border-[#2d2d2d] select-none shadow-md">
        <div class="flex items-center gap-3">
            <span class="inline-block w-3 h-3 rounded-full bg-red-500"></span>
            <span class="inline-block w-3 h-3 rounded-full bg-yellow-500"></span>
            <span class="inline-block w-3 h-3 rounded-full bg-green-500"></span>
            <span class="font-bold text-base text-white tracking-wide ml-2">Code Workspace — <?= htmlspecialchars($project_title) ?></span>
        </div>
        
        <!-- INFORMASI DURASI, TANGGAL & COUNTDOWN TIMER -->
        <div class="flex items-center gap-4 text-gray-300 font-medium text-xs">
            <div id="ai-status-badge" class="hidden sm:flex items-center gap-1.5 bg-red-950/60 px-3 py-1.5 rounded border border-red-800 text-red-300 animate-pulse">
                <span>🛡️ AI Proctoring Active</span>
            </div>

            <div class="hidden sm:flex items-center gap-1.5 bg-[#2d2d2d] px-3 py-1.5 rounded border border-[#444]">
                <span class="text-gray-400">⏱️ Durasi:</span>
                <span class="font-mono font-bold text-white"><?= $duration_minutes ?> Menit</span>
            </div>

            <div class="flex items-center gap-2 bg-[#2d2d2d] px-3 py-1.5 rounded border border-[#444]">
                <span class="text-gray-400">⏳ Sisa Waktu:</span>
                <span id="countdown-timer" class="font-mono font-bold text-yellow-400 text-sm">--:--:--</span>
            </div>

            <div class="hidden md:flex items-center gap-2 text-gray-400">
                <span>📅 <span id="current-date"></span></span>
            </div>
        </div>
    </div>

    <!-- WORKSPACE UTAMA (FORM) -->
    <form id="code-form" action="../../controllers/student/finish_exam.php" method="POST" class="flex-1 flex overflow-hidden">

        <!-- Hidden inputs yang wajib ada agar finish_exam.php menerima data dengan benar -->
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
        <input type="hidden" name="room_id" value="<?= htmlspecialchars($room_id) ?>">
        <input type="hidden" name="language" id="language-hidden" value="python">
        <input type="hidden" name="code_input" id="hidden_code_input">
        <input type="hidden" name="flight_time_data" id="flight-time-input">

        <!-- KIRI: SIDEBAR (EXPLORER & CATATAN PROJECT) -->
        <div class="w-80 bg-[#252526] border-r border-[#333333] flex flex-col select-none">
            
            <div class="bg-[#2d2d2d] flex border-b border-[#333333] text-[11px] font-bold">
                <button type="button" onclick="switchSidebarTab('explorer')" id="tab-btn-explorer" class="flex-1 py-2 px-3 text-center text-white bg-[#252526] border-t-2 border-[#007acc] transition-all">📁 FILES</button>
                <button type="button" onclick="switchSidebarTab('notes')" id="tab-btn-notes" class="flex-1 py-2 px-3 text-center text-gray-400 hover:text-white transition-all">📝 CATATAN</button>
            </div>

            <!-- PANEL 1: PROJECT EXPLORER -->
            <div id="sidebar-panel-explorer" class="flex-1 flex flex-col overflow-hidden">
                <div class="px-4 py-2.5 text-[11px] font-bold tracking-wider text-gray-400 uppercase flex justify-between items-center border-b border-[#2d2d2d]">
                    <span>EXPLORER</span>
                    <button type="button" onclick="addNewFilePrompt()" class="hover:text-white text-gray-400 text-sm font-bold" title="New File">+</button>
                </div>

                <div class="flex-1 overflow-y-auto p-2 text-xs">
                    <div class="mb-3">
                        <div class="text-gray-300 font-semibold px-2 py-1 flex items-center gap-1.5 cursor-pointer">
                            <span>▼</span> 📁 <span>PROJECT_ROOT</span>
                        </div>
                        <div class="pl-4 space-y-1 mt-1" id="file-list-container">
                            <div id="file-solution.py" class="text-blue-400 bg-[#37373d] px-2 py-1 rounded flex items-center gap-1.5 cursor-pointer" onclick="switchFile('solution.py')">
                                <span>📄</span> <span class="file-item-name">solution.py</span>
                            </div>
                            <div id="file-Main.java" class="text-gray-400 hover:bg-[#2a2d2e] px-2 py-1 rounded flex items-center gap-1.5 cursor-pointer" onclick="switchFile('Main.java')">
                                <span>📄</span> <span class="file-item-name">Main.java</span>
                            </div>
                            <div id="file-script.js" class="text-gray-400 hover:bg-[#2a2d2e] px-2 py-1 rounded flex items-center gap-1.5 cursor-pointer" onclick="switchFile('script.js')">
                                <span>📄</span> <span class="file-item-name">script.js</span>
                            </div>
                        </div>
                    </div>

                    <div class="border-t border-[#333333] pt-3 mt-3 px-1">
                        <p class="font-semibold text-gray-200 mb-2">⚙️ Informasi Editor:</p>
                        <div class="text-gray-400 space-y-2 text-[11px] leading-relaxed">
                            <p>1. Mendukung Auto-indent, autocomplete, & syntax highlighting.</p>
                            <p>2. Gunakan terminal di bawah untuk menguji baris kode.</p>
                            <div class="bg-[#1e1e1e] p-2 rounded border border-[#333333] font-mono text-[10px] text-green-400">
                                > Monaco VS Code Engine v0.33
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- PANEL 2: CATATAN & DESKRIPSI PROJECT -->
            <div id="sidebar-panel-notes" class="flex-1 flex flex-col overflow-y-auto p-4 text-xs space-y-3 hidden">
                <div class="text-sm font-bold text-white border-b border-[#333] pb-2">
                    <?= htmlspecialchars($project_title) ?>
                </div>
                <div>
                    <span class="text-[10px] bg-blue-900 text-blue-200 px-2 py-0.5 rounded font-mono uppercase">Deskripsi Project</span>
                    <div class="text-gray-300 mt-2 leading-relaxed whitespace-pre-line text-xs bg-[#1e1e1e] p-3 rounded border border-[#333]">
                        <?= nl2br(htmlspecialchars($project_description)) ?>
                    </div>
                </div>
                <div>
                    <span class="text-[10px] bg-emerald-900 text-emerald-200 px-2 py-0.5 rounded font-mono uppercase">Instruksi Soal</span>
                    <div class="text-emerald-300 font-mono mt-2 leading-relaxed whitespace-pre-wrap text-xs bg-[#1e1e1e] p-3 rounded border border-[#333]">
                        <?= htmlspecialchars($question_text) ?>
                    </div>
                </div>
            </div>

            <!-- User Footer Info -->
            <div class="p-4 bg-[#181818] border-t-2 border-[#333333] flex flex-col justify-between gap-2 shadow-2xl">
                <div class="text-gray-400 text-xs font-bold tracking-wider uppercase flex justify-between items-center">
                    <span>USER SESSION:</span>
                    <span class="w-2.5 h-2.5 rounded-full bg-green-500" title="Connected"></span>
                </div>
                <div class="font-bold text-white text-base tracking-wide truncate"><?= htmlspecialchars($user_name) ?></div>
                <div class="text-gray-300 font-mono text-xs font-semibold tracking-wider bg-[#222222] px-2.5 py-1 rounded border border-[#333]"><?= htmlspecialchars($identity_number) ?></div>
            </div>
        </div>

        <!-- TENGAH: MONACO CODE EDITOR AREA + TOP CONTROLS -->
        <div class="flex-1 flex flex-col bg-[#1e1e1e] overflow-hidden relative">
            
            <div class="bg-[#2d2d2d] h-11 flex items-center justify-between px-4 border-b border-[#252526]">
                <div class="flex items-center gap-2">
                    <div class="bg-[#1e1e1e] text-white px-4 py-2 text-xs border-t-2 border-[#007acc] flex items-center gap-2">
                        <span>📄</span> <span id="active-filename">solution.py</span>
                        <span id="detected-lang-badge" class="ml-2 text-[10px] bg-[#0e639c] text-white px-1.5 py-0.5 rounded uppercase font-mono">python</span>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <button type="button" onclick="toggleAndRunCode()" class="bg-[#388a34] hover:bg-[#469542] active:scale-95 text-white font-medium px-3.5 py-1.5 rounded text-xs transition-all flex items-center gap-1.5 shadow">
                        ▶ Run Code
                    </button>
                    <button type="submit" onclick="return prepareSubmit()" class="bg-[#0e639c] hover:bg-[#1177bb] active:scale-95 text-white font-medium px-3.5 py-1.5 rounded text-xs transition-all flex items-center gap-1.5 shadow">
                        💾 Save & Submit
                    </button>
                    <button type="button" onclick="toggleTerminal()" class="bg-[#333333] hover:bg-[#444] text-gray-300 px-2.5 py-1.5 rounded text-xs transition-all border border-[#555]" title="Toggle Terminal">
                        💻 Terminal
                    </button>
                </div>
            </div>

            <!-- CONTAINER UTAMA MONACO EDITOR -->
            <div id="monaco-editor-container" class="flex-1 w-full h-full"></div>

            <!-- TERMINAL BAWAH -->
            <div id="terminal-container" class="h-52 bg-[#181818] border-t border-[#333333] flex flex-col font-mono text-xs hidden transition-all">
                <div class="bg-[#2d2d2d] px-3 py-1.5 flex justify-between items-center text-[11px] text-gray-300 border-b border-[#333333]">
                    <span class="font-bold flex items-center gap-1">💻 INTEGRATED TERMINAL</span>
                    <div class="flex items-center gap-3">
                        <span id="terminal-status" class="text-yellow-400">Idle</span>
                        <button type="button" onclick="toggleTerminal()" class="text-gray-400 hover:text-white font-bold text-sm">✕</button>
                    </div>
                </div>

                <div id="terminal-output" class="flex-1 p-3 overflow-y-auto space-y-1 text-gray-300 text-[11px]">
                    <p class="text-gray-500">// Compiler sandbox ready. Press 'Run Code' to execute.</p>
                </div>

                <div class="p-2 bg-[#252526] border-t border-[#333333] flex items-center gap-2">
                    <input type="text" id="stdin-input" placeholder="Masukkan stdin (opsional)..." class="flex-1 bg-[#1e1e1e] text-white border border-[#444] rounded px-2 py-1 text-[11px] focus:outline-none focus:border-[#007acc]">
                    <button type="button" onclick="runCodeAdvanced()" class="bg-[#333333] hover:bg-[#444444] text-white px-3 py-1 rounded text-[11px] transition-all border border-[#555]">
                        Execute
                    </button>
                </div>
            </div>

        </div>

    </form>

    <!-- SCRIPT UTAMA EDITOR, COUNTDOWN TIMER, AI PROCTORING & KEYSTROKE DYNAMICS -->
    <script>
        // --- 0. COUNTDOWN TIMER ---
        let totalSeconds = <?= $duration_minutes ?> * 60;
        
        function updateCountdown() {
            const timerDisplay = document.getElementById('countdown-timer');
            if (totalSeconds <= 0) {
                timerDisplay.textContent = "Waktu Habis!";
                timerDisplay.className = "font-mono font-bold text-red-500 text-sm animate-pulse";
                isFinishing = true;
                alert("Waktu habis! Kode Anda disubmit otomatis.");
                document.getElementById('hidden_code_input').value = getEditorValue();
                document.getElementById('flight-time-input').value = JSON.stringify(keystrokeLogs);
                document.getElementById('code-form').submit(); 
                return;
            }

            let hours = Math.floor(totalSeconds / 3600);
            let minutes = Math.floor((totalSeconds % 3600) / 60);
            let seconds = totalSeconds % 60;

            timerDisplay.textContent = 
                String(hours).padStart(2, '0') + ":" + 
                String(minutes).padStart(2, '0') + ":" + 
                String(seconds).padStart(2, '0');

            totalSeconds--;
        }

        setInterval(updateCountdown, 1000);
        updateCountdown();

        // Tanggal Hari Ini
        const optionsDate = { weekday: 'short', year: 'numeric', month: 'short', day: 'numeric' };
        document.getElementById('current-date').textContent = new Date().toLocaleDateString('id-ID', optionsDate);

        // --- 0.1 SISTEM PEREKAM JEDA KETIKAN (KEYSTROKE DYNAMICS) ---
        let keystrokeLogs = [];
        let lastKeyDownTime = null;

        document.addEventListener('keydown', function(e) {
            let currentTime = performance.now();
            
            if (lastKeyDownTime !== null) {
                let flightTime = currentTime - lastKeyDownTime; 
                if (keystrokeLogs.length > 2000) keystrokeLogs.shift();
                
                keystrokeLogs.push({
                    key: e.key.length === 1 ? 'CHAR' : e.key,
                    flight_ms: Math.round(flightTime),
                    timestamp: Date.now()
                });
            }
            lastKeyDownTime = currentTime;
        });

        // --- 0.2 INTEGRASI AI PROCTORING & MEDIA RECORDER ---
        let mediaRecorder;
        let recordedChunks = [];
        let isRecordingClip = false;
        let cocoModel = null;
        let isProctoringActive = false;

        window.addEventListener('DOMContentLoaded', async () => {
            try {
                if (typeof tf === 'undefined' || typeof cocoSsd === 'undefined') {
                    console.warn("Library AI Proctoring tidak tersedia. Melanjutkan tanpa AI Proctoring.");
                    return;
                }

                const stream = await navigator.mediaDevices.getUserMedia({ video: { width: 640, height: 480 }, audio: false });
                const videoElement = document.getElementById('proctoring-video');
                videoElement.srcObject = stream;
                videoElement.play();

                setupMediaRecorder(stream);

                console.log("Memuat model AI Proctoring (COCO-SSD)...");
                cocoModel = await cocoSsd.load();
                console.log("Model AI siap!");
                document.getElementById('ai-status-badge').classList.remove('hidden');
                isProctoringActive = true;

                setInterval(runAIProctoringLoop, 1500);

            } catch (err) {
                console.warn("Gagal menginisialisasi kamera/proctoring:", err);
            }
        });

        function setupMediaRecorder(stream) {
            try {
                const options = { mimeType: 'video/webm; codecs=vp8' };
                mediaRecorder = new MediaRecorder(stream, options);
            } catch (e) {
                mediaRecorder = new MediaRecorder(stream);
            }

            mediaRecorder.ondataavailable = function(event) {
                if (event.data && event.data.size > 0) {
                    recordedChunks.push(event.data);
                }
            };

            mediaRecorder.onstop = function() {
                const blob = new Blob(recordedChunks, { type: 'video/webm' });
                uploadViolationClip(blob, currentViolationType);
                recordedChunks = [];
                isRecordingClip = false;
            };
        }

        let currentViolationType = "Unknown Violation";

        async function runAIProctoringLoop() {
            if (!isProctoringActive || !cocoModel || !examStarted || isFinishing) return;
            const videoElement = document.getElementById('proctoring-video');
            if (videoElement.readyState !== 4) return;

            try {
                const predictions = await cocoModel.detect(videoElement);
                let phoneDetected = false;

                predictions.forEach(prediction => {
                    if (prediction.class === 'cell phone' && prediction.score > 0.55) {
                        phoneDetected = true;
                    }
                });

                if (phoneDetected && !isRecordingClip) {
                    triggerInstantViolation("Handphone Terdeteksi di Kamera");
                }
            } catch (e) {
                console.error("Error pada AI loop:", e);
            }
        }

        function triggerInstantViolation(violationType) {
            if (isRecordingClip || !examStarted || isFinishing) return;
            isRecordingClip = true;
            currentViolationType = violationType;
            recordedChunks = [];

            reportViolation(violationType);

            if (mediaRecorder && mediaRecorder.state === "inactive") {
                mediaRecorder.start();
                setTimeout(() => {
                    if (mediaRecorder.state === "recording") {
                        mediaRecorder.stop();
                    }
                }, 5000);
            }
        }

        function uploadViolationClip(videoBlob, violationType) {
            const formData = new FormData();
            formData.append('room_id', '<?= $room_id ?>');
            formData.append('violation_type', violationType);
            formData.append('csrf_token', '<?= $_SESSION['csrf_token'] ?>');
            formData.append('video_file', videoBlob, 'violation_evidence.webm');
            formData.append('timestamp', new Date().toISOString());

            fetch('../../controllers/student/report_violation.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                console.log("Klip video bukti pelanggaran berhasil disimpan:", data);
            })
            .catch(error => {
                console.error("Gagal mengunggah klip video:", error);
            });
        }

        // --- 0.3 SISTEM PROTEKSI ---
        const ROOM_ID        = <?= json_encode($room_id) ?>;
        const CSRF_TOKEN     = <?= json_encode($_SESSION['csrf_token']) ?>;
        const VIOLATION_URL  = '../../controllers/student/report_violation.php';
        const LEAVE_URL      = '../../controllers/student/leave_exam.php';

        let violationCount = 0;
        let examStarted     = false;
        let isFinishing     = false; 

        const overlay    = document.getElementById('start-overlay');
        const banner     = document.getElementById('violation-banner');
        const bannerText = document.getElementById('violation-text');
        const countEl    = document.getElementById('violation-count');

        function showBanner(msg) {
            bannerText.textContent = msg;
            banner.classList.remove('hidden');
            setTimeout(() => banner.classList.add('hidden'), 4000);
        }

        async function reportViolation(reason) {
            if (!examStarted || isFinishing) return;
            try {
                const res = await fetch(VIOLATION_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ room_id: ROOM_ID, csrf_token: CSRF_TOKEN, reason })
                });
                const data = await res.json();
                if (data.count !== undefined) {
                    violationCount = data.count;
                    countEl.textContent = violationCount;
                }
                if (data.forfeited) {
                    isFinishing = true;
                    alert('Ujian dinyatakan gugur karena terlalu banyak pelanggaran.');
                    window.location.href = '../student/dashboard.php';
                } else {
                    showBanner(`Pelanggaran terdeteksi (${reason}). Percobaan ke-${violationCount} dari 3.`);
                }
            } catch (e) {
                console.error('Gagal melapor pelanggaran', e);
            }
        }

        document.getElementById('start-btn').addEventListener('click', async () => {
            try {
                await document.documentElement.requestFullscreen();
            } catch (e) {
                console.warn('Fullscreen ditolak/tidak didukung, ujian tetap lanjut.', e);
            }
            overlay.classList.add('hidden');
            examStarted = true;
        });

        document.addEventListener('visibilitychange', () => {
            if (document.hidden && examStarted && !isFinishing) {
                reportViolation('pindah tab / minimize');
            }
        });

        document.addEventListener('fullscreenchange', () => {
            if (examStarted && !isFinishing && !document.fullscreenElement) {
                reportViolation('keluar fullscreen');
            }
        });

        window.addEventListener('beforeunload', (e) => {
            if (!examStarted || isFinishing) return;
            const payload = new URLSearchParams({ room_id: ROOM_ID, csrf_token: CSRF_TOKEN }).toString();
            navigator.sendBeacon(LEAVE_URL, new Blob([payload], { type: 'application/x-www-form-urlencoded' }));
            e.preventDefault();
            e.returnValue = 'Meninggalkan halaman ini akan menggugurkan ujian Anda. Yakin?';
            return e.returnValue;
        });

        // --- 1. FUNGSI SWITCH TAB SIDEBAR ---
        function switchSidebarTab(tabName) {
            const explorerPanel = document.getElementById('sidebar-panel-explorer');
            const notesPanel = document.getElementById('sidebar-panel-notes');
            const explorerBtn = document.getElementById('tab-btn-explorer');
            const notesBtn = document.getElementById('tab-btn-notes');

            if (tabName === 'explorer') {
                explorerPanel.classList.remove('hidden');
                notesPanel.classList.add('hidden');
                explorerBtn.className = "flex-1 py-2 px-3 text-center text-white bg-[#252526] border-t-2 border-[#007acc] transition-all";
                notesBtn.className = "flex-1 py-2 px-3 text-center text-gray-400 hover:text-white transition-all";
            } else {
                explorerPanel.classList.add('hidden');
                notesPanel.classList.remove('hidden');
                notesBtn.className = "flex-1 py-2 px-3 text-center text-white bg-[#252526] border-t-2 border-[#007acc] transition-all";
                explorerBtn.className = "flex-1 py-2 px-3 text-center text-gray-400 hover:text-white transition-all";
            }
        }

        // --- 2. INISIALISASI MONACO EDITOR & SAFE GETTERS ---
        let editor = null;
        const extensionMap = {
            'py': 'python', 'rb': 'ruby', 'js': 'javascript', 
            'php': 'php', 'java': 'java', 'cpp': 'cpp', 'c': 'c', 'go': 'go'
        };

        const templates = {
            python: 'print("Hello World")',
            ruby: 'puts "Hello World"',
            javascript: 'console.log("Hello World");',
            php: '<?php\necho "Hello World";',
            cpp: '#include <iostream>\n\nint main() {\n    std::cout << "Hello World";\n    return 0;\n}',
            java: 'public class Main {\n    public static void main(String[] args) {\n        System.out.println("Hello World");\n    }\n}',
            c: '#include <stdio.h>\n\nint main() {\n    printf("Hello World");\n    return 0;\n}'
        };

        let fileStorage = {
            'solution.py': templates.python,
            'Main.java': templates.java,
            'script.js': templates.javascript
        };

        let currentActiveFile = 'solution.py';

        // Fungsi aman untuk mengambil teks kode saat ini (berfungsi untuk Monaco atau Textarea Fallback)
        function getEditorValue() {
            if (editor !== null && typeof editor.getValue === 'function') {
                return editor.getValue();
            }
            const ta = document.getElementById('fallback-ta');
            return ta ? ta.value : (fileStorage[currentActiveFile] || '');
        }

        // Fungsi aman untuk menetapkan teks kode
        function setEditorValue(val) {
            if (editor !== null && typeof editor.setValue === 'function') {
                editor.setValue(val);
            } else {
                const ta = document.getElementById('fallback-ta');
                if (ta) ta.value = val;
            }
        }

        try {
            require.config({ 
                paths: { 'vs': 'https://unpkg.com/monaco-editor@0.33.0/min/vs' },
                waitSeconds: 15
            });
            
            require(['vs/editor/editor.main'], function() {
                editor = monaco.editor.create(document.getElementById('monaco-editor-container'), {
                    value: fileStorage['solution.py'],
                    language: 'python',
                    theme: 'vs-dark',
                    automaticLayout: true,
                    fontSize: 14,
                    fontFamily: 'JetBrains Mono, monospace',
                    minimap: { enabled: true },
                    scrollBeyondLastLine: false,
                    roundedSelection: false,
                    cursorBlinking: 'smooth'
                });

                editor.onDidChangeModelContent(function() {
                    fileStorage[currentActiveFile] = editor.getValue();
                });
            }, function(err) {
                console.warn("Gagal memuat Monaco Editor CDN. Mengaktifkan fallback textarea.");
                activateFallbackTextarea();
            });
        } catch (e) {
            activateFallbackTextarea();
        }

        function activateFallbackTextarea() {
            const container = document.getElementById('monaco-editor-container');
            container.innerHTML = `<textarea id="fallback-ta" class="w-full h-full bg-[#1e1e1e] text-[#cccccc] p-4 font-mono text-sm focus:outline-none resize-none"></textarea>`;
            const ta = document.getElementById('fallback-ta');
            ta.value = fileStorage[currentActiveFile];
            ta.addEventListener('input', function() {
                fileStorage[currentActiveFile] = ta.value;
            });
        }

        function detectLanguage(filename) {
            let ext = filename.split('.').pop().toLowerCase();
            return extensionMap[ext] || 'python';
        }

        function switchFile(filename) {
            fileStorage[currentActiveFile] = getEditorValue();

            document.querySelectorAll('#file-list-container > div').forEach(el => {
                el.className = "text-gray-400 hover:bg-[#2a2d2e] px-2 py-1 rounded flex items-center gap-1.5 cursor-pointer";
            });
            let activeEl = document.getElementById('file-' + filename);
            if (activeEl) {
                activeEl.className = "text-blue-400 bg-[#37373d] px-2 py-1 rounded flex items-center gap-1.5 cursor-pointer";
            }

            currentActiveFile = filename;
            document.getElementById('active-filename').textContent = filename;
            
            let detectedLang = detectLanguage(filename);
            document.getElementById('language-hidden').value = detectedLang;
            document.getElementById('detected-lang-badge').textContent = detectedLang;

            if (fileStorage[filename] === undefined) {
                fileStorage[filename] = templates[detectedLang] || '// Tulis kode Anda di sini';
            }

            if (editor !== null && typeof editor.setModel === 'function') {
                let model = editor.getModel();
                monaco.editor.setModelLanguage(model, detectedLang);
                editor.setValue(fileStorage[filename]);
            } else {
                setEditorValue(fileStorage[filename]);
            }
        }

        function addNewFilePrompt() {
            let fileName = prompt("Masukkan nama file baru (contoh: Main.java, solution.cpp, script.py):");
            if (fileName && fileName.trim() !== "") {
                let cleanName = fileName.trim();
                let detectedLang = detectLanguage(cleanName);

                if (fileStorage[cleanName] === undefined) {
                    fileStorage[cleanName] = templates[detectedLang] || '// Tulis kode Anda di sini';
                }

                const container = document.getElementById('file-list-container');
                let newFileDiv = document.createElement('div');
                newFileDiv.id = 'file-' + cleanName;
                newFileDiv.className = "text-gray-400 hover:bg-[#2a2d2e] px-2 py-1 rounded flex items-center gap-1.5 cursor-pointer";
                newFileDiv.innerHTML = `<span>📄</span> <span>${cleanName}</span>`;
                newFileDiv.onclick = function() { switchFile(cleanName); };
                container.appendChild(newFileDiv);
                
                switchFile(cleanName);
            }
        }

        // --- 3. PREPARE SUBMIT & KEYSTROKE PACKING ---
        function prepareSubmit() {
            let codeVal = getEditorValue();

            if (confirm('Yakin ingin menyimpan dan mensubmit perubahan kode ini?')) {
                isFinishing = true; 
                document.getElementById('hidden_code_input').value = codeVal;
                document.getElementById('flight-time-input').value = JSON.stringify(keystrokeLogs);
                return true;
            }
            return false;
        }

        function toggleTerminal() {
            const term = document.getElementById('terminal-container');
            term.classList.toggle('hidden');
            if (editor !== null && typeof editor.layout === 'function') {
                editor.layout();
            }
        }

        function toggleAndRunCode() {
            const term = document.getElementById('terminal-container');
            if (term.classList.contains('hidden')) {
                term.classList.remove('hidden');
            }
            if (editor !== null && typeof editor.layout === 'function') {
                editor.layout();
            }
            runCodeAdvanced();
        }

        // --- 4. ADVANCED RUNTIME EMULATOR (MULTI-LANGUAGE) ---
        async function runCodeAdvanced() {
            let code = getEditorValue();
            let lang = document.getElementById('language-hidden').value;
            let stdinVal = document.getElementById('stdin-input').value;
            
            const terminalOutput = document.getElementById('terminal-output');
            const terminalStatus = document.getElementById('terminal-status');

            terminalOutput.innerHTML = `<p class="text-gray-500">// Compiling & executing ${lang.toUpperCase()} source code...</p>`;
            terminalStatus.textContent = "Compiling...";

            if (code.trim() === "") {
                setTimeout(() => {
                    terminalOutput.innerHTML = `<p class="text-red-400">Error: Source code is empty.</p>`;
                    terminalStatus.textContent = "Failed";
                }, 300);
                return;
            }

            setTimeout(() => {
                let logs = [];
                let outputText = "";

                try {
                    if (lang === 'python') {
                        let lines = code.split('\n');
                        let executed = false;
                        lines.forEach(line => {
                            let trimmed = line.trim();
                            let match = trimmed.match(/^print\((.*)\)$/);
                            if (match) {
                                let content = match[1].trim();
                                if ((content.startsWith('"') && content.endsWith('"')) || (content.startsWith("'") && content.endsWith("'"))) {
                                    content = content.slice(1, -1);
                                } else {
                                    try { content = eval(content); } catch(e) {}
                                }
                                logs.push(content);
                                executed = true;
                            }
                        });
                        if (!executed) logs.push("[Python Process Completed - No stdout output]");
                        outputText = logs.join('\n');
                    } 
                    else if (lang === 'javascript') {
                        const originalConsoleLog = console.log;
                        console.log = function(...args) {
                            logs.push(args.map(arg => typeof arg === 'object' ? JSON.stringify(arg, null, 2) : arg).join(' '));
                        };
                        let runFunc = new Function(code);
                        let result = runFunc();
                        console.log = originalConsoleLog; 

                        if (logs.length > 0) {
                            outputText = logs.join('\n');
                        } else if (result !== undefined) {
                            outputText = String(result);
                        } else {
                            outputText = "Program executed successfully (no stdout output).";
                        }
                    }
                    else if (lang === 'php') {
                        let cleanCode = code.replace(/<\?php/g, '').replace(/\?>/g, '');
                        let lines = cleanCode.split('\n');
                        lines.forEach(line => {
                            let trimmed = line.trim();
                            let matchEcho = trimmed.match(/^echo\s+(.*);?$/);
                            if (matchEcho) {
                                let content = matchEcho[1].replace(/;$/, '').trim();
                                if ((content.startsWith('"') && content.endsWith('"')) || (content.startsWith("'") && content.endsWith("'"))) {
                                    content = content.slice(1, -1);
                                }
                                logs.push(content);
                            }
                        });
                        outputText = logs.length > 0 ? logs.join('\n') : "[PHP Process Completed]";
                    }
                    else {
                        outputText = `[${lang.toUpperCase()} Simulator]: Hello World (Mock Execution Success)`;
                    }

                    if (stdinVal.trim() !== "") {
                        outputText = `Input diterima: ${stdinVal}\n` + outputText;
                    }

                    terminalOutput.innerHTML = `
                        <p class="text-gray-400">// Executing ${currentActiveFile}...</p>
                        <pre class="text-green-400 whitespace-pre-wrap mt-1">${escapeHtml(outputText)}</pre>
                        <p class="text-gray-500 text-[10px] mt-2">=== Process finished with exit code 0 (Time: 0.03s) ===</p>
                    `;
                    terminalStatus.textContent = "Success (0.03s)";
                    terminalOutput.scrollTop = terminalOutput.scrollHeight;

                } catch (err) {
                    terminalOutput.innerHTML = `
                        <p class="text-red-400 font-bold">RuntimeError: ${escapeHtml(err.message)}</p>
                        <p class="text-gray-500 text-[10px]">Process finished with exit code 1</p>
                    `;
                    terminalStatus.textContent = "Runtime Error";
                    terminalOutput.scrollTop = terminalOutput.scrollHeight;
                }
            }, 400);
        }

        function escapeHtml(text) {
            return text
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }
    </script>
</body>
</html>