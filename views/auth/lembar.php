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
$user_id         = $_SESSION['user_id'] ?? null;
$user_email      = $_SESSION['email'] ?? 'SISWA';
$user_name       = $_SESSION['name'] ?? 'User Coding';
$identity_number = $_SESSION['identity_number'] ?? 'DEV-001';

// Ambil project/room_id dari parameter URL (opsional)
$room_id = isset($_GET['room_id']) ? intval($_GET['room_id']) : ($_SESSION['room_id'] ?? 1);

// Default Fallback
$project_title       = "Workspace Project";
$duration_minutes    = 90;
$project_description = "Tulis dan kembangkan kode program Anda di sini.";
$question_text       = "// Tulis solusi koding Anda di sini\nprint('Hello World');";

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
    error_log('[LEMBAR ROOM FETCH ERROR] ' . $e->getMessage());
}

// Hitung SISA WAKTU berdasarkan waktu join sebenarnya di database
$total_duration_seconds = $duration_minutes * 60;
$joined_at_timestamp    = strtotime($exam_session['joined_at'] ?? 'now');
$elapsed_seconds        = max(0, time() - $joined_at_timestamp);
$remaining_seconds      = max(0, $total_duration_seconds - $elapsed_seconds);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lembar Pengerjaan - <?= htmlspecialchars($project_title) ?></title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
    
    <!-- Monaco Editor Loader & CSS -->
    <script src="https://unpkg.com/monaco-editor@0.33.0/min/vs/loader.js"></script>
    <link rel="stylesheet" data-name="vs/editor/editor.main" href="https://unpkg.com/monaco-editor@0.33.0/min/vs/editor/editor.main.css">

    <!-- TensorFlow & COCO-SSD -->
    <script src="https://cdn.jsdelivr.net/npm/@tensorflow/tfjs@3.18.0/dist/tf.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@tensorflow-models/coco-ssd"></script>

    <style>
        body { font-family: 'Inter', sans-serif; background-color: #1e1e1e; color: #d4d4d4; }
        .font-mono { font-family: 'JetBrains Mono', monospace; }
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: #1e1e1e; }
        ::-webkit-scrollbar-thumb { background: #424242; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #4f4f4f; }
    </style>
</head>
<body class="h-screen flex flex-col overflow-hidden select-none bg-[#1e1e1e] text-[#d4d4d4]">

    <!-- OVERLAY: Wajib klik dulu supaya browser mengizinkan Fullscreen API -->
    <div id="start-overlay" class="fixed inset-0 z-50 bg-[#1e1e1e]/90 flex items-center justify-center px-6">
        <div class="bg-[#252526] rounded-lg shadow-2xl p-8 max-w-md w-full text-center border border-[#333333]">
            <h1 class="text-lg font-bold text-[#cccccc] mb-2">Siap Memulai Ujian?</h1>
            <p class="text-xs text-[#858585] mb-6 leading-relaxed">
                Ujian berjalan dalam mode layar penuh dengan pengawasan AI. Berpindah tab, keluar fullscreen, atau menutup halaman akan dicatat sebagai pelanggaran. Akumulasi 
                <span class="text-[#f48771] font-semibold">3 kali</span> pelanggaran akan membuat ujian otomatis <span class="text-[#f48771] font-semibold">gugur</span>.
            </p>
            <button id="start-btn" class="bg-[#0e639c] hover:bg-[#1177bb] active:scale-95 text-white font-medium px-6 py-2.5 rounded text-xs transition-all shadow-md">
                Mulai Ujian (Fullscreen)
            </button>
        </div>
    </div>

    <!-- WARNING BANNER: muncul saat pelanggaran terdeteksi -->
    <div id="violation-banner" class="hidden fixed top-0 left-0 right-0 z-40 bg-[#f48771] text-black text-xs font-bold text-center py-2 px-4 shadow-md">
        <span id="violation-text">Pelanggaran terdeteksi.</span>
        <span id="violation-count-wrap"> (Pelanggaran ke-<span id="violation-count">0</span> dari 3)</span>
    </div>

    <!-- HIDDEN WEBCAM ELEMENT UNTUK PROCTORING AI -->
    <video id="proctoring-video" autoplay playsinline muted style="display:none;"></video>

    <!-- KAMERA PENGAWASAN PREVIEW -->
    <div id="webcam-box" class="fixed bottom-4 right-4 z-30 w-36 rounded overflow-hidden border border-[#454545] shadow-2xl bg-black">
        <video id="webcam-preview-video" autoplay muted playsinline class="w-full h-auto block"></video>
        <div id="webcam-status" class="absolute top-1 left-1 flex items-center gap-1 bg-black/70 px-1.5 py-0.5 rounded text-[9px] text-white font-medium">
            <span class="w-1.5 h-1.5 rounded-full bg-red-500 animate-pulse"></span> LIVE
        </div>
    </div>

    <!-- VS CODE TOP ACTIVITY BAR / TITLEBAR -->
    <div class="bg-[#333333] text-[#cccccc] px-4 py-1.5 text-xs flex justify-between items-center border-b border-[#252526] select-none">
        <div class="flex items-center gap-2">
            <span class="w-2.5 h-2.5 rounded-full bg-[#f48771]"></span>
            <span class="font-semibold tracking-wide text-[#ffffff]"><?= htmlspecialchars($project_title) ?></span>
            <span class="text-[#858585] text-[11px] ml-2">| &nbsp; NAMA: <span class="text-[#cccccc]"><?= htmlspecialchars($user_name) ?> (<?= htmlspecialchars($identity_number) ?>)</span></span>
        </div>

        <div class="flex items-center gap-3">
            <div id="ai-status-badge" class="hidden sm:flex items-center gap-1 bg-[#252526] px-2.5 py-0.5 rounded text-[10px] text-[#4ec9b0] border border-[#3c3c3c]">
                <span>🛡️ AI Active</span>
            </div>
            <div class="bg-[#252526] px-3 py-1 rounded text-xs border border-[#3c3c3c] font-mono text-[#4ec9b0]">
                TIME: <span id="countdown-timer" class="font-bold text-[#ce9178]">00:00:00</span>
            </div>
        </div>
    </div>

    <!-- FORM PENGERJAAN UTAMA (LAYOUT VS CODE 2 KOLOM: KIRI EXPLORER, KANAN EDITOR+TERMINAL) -->
    <form id="code-form" action="../../controllers/student/finish_exam.php" method="POST" class="flex-1 grid grid-cols-1 lg:grid-cols-4 gap-0 overflow-hidden bg-[#1e1e1e]">
        
        <!-- Hidden Inputs untuk Controller Finish Exam -->
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
        <input type="hidden" name="room_id" value="<?= htmlspecialchars($room_id) ?>">
        <input type="hidden" name="language" id="language-hidden" value="python">
        <!-- Menggunakan name="answer_code" sesuai dengan finish_exam.php -->
        <input type="hidden" name="answer_code" id="hidden_code_input">
        <input type="hidden" name="flight_time_data" id="flight-time-input">

        <!-- PANEL 1: EXPLORER & DESKRIPSI SOAL (KIRI - 1 Kolom dari 4) -->
        <div class="bg-[#252526] border-r border-[#333333] flex flex-col justify-between overflow-y-auto text-xs">
            <div>
                <!-- VS Code Explorer Header -->
                <div class="px-4 py-2 text-[11px] font-bold text-[#bbbbbb] tracking-wider uppercase border-b border-[#333333] flex justify-between items-center bg-[#2d2d2d]">
                    <span>EXPLORER</span>
                    <button type="button" onclick="addNewFilePrompt()" class="text-[#4ec9b0] hover:text-white font-bold bg-[#333333] hover:bg-[#3c3c3c] px-2 py-0.5 rounded transition-all text-[10px]" title="Tambah File">+ New File</button>
                </div>

                <!-- File Manager Tree View dengan Tombol Hapus -->
                <div class="p-2 space-y-0.5 border-b border-[#333333] text-[#cccccc]" id="file-list-container">
                    <div id="file-solution.py" class="text-[#ffffff] bg-[#37373d] px-2.5 py-1 rounded flex items-center justify-between cursor-pointer transition-all group" onclick="switchFile('solution.py')">
                        <div class="flex items-center gap-2 truncate"><span>📄</span> <span class="file-item-name truncate">solution.py</span></div>
                        <button type="button" onclick="deleteFile(event, 'solution.py')" class="opacity-0 group-hover:opacity-100 text-[#858585] hover:text-[#f48771] px-1 rounded text-[10px]" title="Hapus File">🗑</button>
                    </div>
                    <div id="file-Main.java" class="text-[#858585] hover:bg-[#2a2d2e] hover:text-[#cccccc] px-2.5 py-1 rounded flex items-center justify-between cursor-pointer transition-all group" onclick="switchFile('Main.java')">
                        <div class="flex items-center gap-2 truncate"><span>📄</span> <span class="file-item-name truncate">Main.java</span></div>
                        <button type="button" onclick="deleteFile(event, 'Main.java')" class="opacity-0 group-hover:opacity-100 text-[#858585] hover:text-[#f48771] px-1 rounded text-[10px]" title="Hapus File">🗑</button>
                    </div>
                    <div id="file-script.js" class="text-[#858585] hover:bg-[#2a2d2e] hover:text-[#cccccc] px-2.5 py-1 rounded flex items-center justify-between cursor-pointer transition-all group" onclick="switchFile('script.js')">
                        <div class="flex items-center gap-2 truncate"><span>📄</span> <span class="file-item-name truncate">script.js</span></div>
                        <button type="button" onclick="deleteFile(event, 'script.js')" class="opacity-0 group-hover:opacity-100 text-[#858585] hover:text-[#f48771] px-1 rounded text-[10px]" title="Hapus File">🗑</button>
                    </div>
                </div>

                <!-- Deskripsi Soal & Instruksi -->
                <div class="p-4 space-y-3 text-[#cccccc]">
                    <div>
                        <strong class="text-[#9cdcfe] text-[11px] tracking-wider block mb-1 uppercase">// DESKRIPSI PROJECT:</strong>
                        <div class="whitespace-pre-line leading-relaxed text-xs bg-[#1e1e1e] p-3 rounded border border-[#333333] text-[#d4d4d4]"><?= nl2br(htmlspecialchars($project_description)) ?></div>
                    </div>
                    
                    <div>
                        <strong class="text-[#9cdcfe] text-[11px] tracking-wider block mb-1 uppercase">// INSTRUKSI SOAL:</strong>
                        <div class="whitespace-pre-wrap leading-relaxed text-xs font-mono bg-[#1e1e1e] p-3 rounded border border-[#333333] text-[#ce9178]"><?= htmlspecialchars($question_text) ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- AREA KANAN (EDITOR & TERMINAL BAWAH - Mengambil 3 Kolom) -->
        <div class="lg:col-span-3 flex flex-col h-full overflow-hidden bg-[#1e1e1e]">
            
            <!-- EDITOR CONTAINER (Bagian Atas) -->
            <div class="flex-1 flex flex-col overflow-hidden relative">
                <!-- Editor Tab Bar -->
                <div class="bg-[#2d2d2d] px-3 py-1.5 border-b border-[#333333] flex justify-between items-center text-xs">
                    <div class="flex items-center gap-3">
                        <span class="font-semibold text-[#cccccc] flex items-center gap-2">
                            <span id="active-filename" class="text-[#ffffff]">solution.py</span>
                            <span id="detected-lang-badge" class="ml-1 text-[9px] bg-[#007acc] text-white px-1.5 py-0.5 rounded uppercase font-mono">python</span>
                        </span>
                    </div>
                    
                    <div class="flex items-center gap-2">
                        <!-- Tombol Toggle Terminal Manual -->
                        <button type="button" onclick="toggleTerminal()" class="bg-[#333333] hover:bg-[#3c3c3c] text-[#cccccc] px-2.5 py-1 rounded text-[11px] transition-all border border-[#454545] flex items-center gap-1">
                            <span>💻</span> <span id="terminal-toggle-label">Terminal</span>
                        </button>
                        <!-- Tombol Run Code -->
                        <button type="button" onclick="toggleAndRunCode()" class="bg-[#0e639c] hover:bg-[#1177bb] active:scale-95 text-white font-medium px-3 py-1 rounded text-[11px] transition-all flex items-center gap-1 shadow-sm">
                            ▶ Run Code
                        </button>
                        <!-- Tombol Submit Ujian -->
                        <button type="submit" onclick="return prepareSubmit()" class="bg-[#2ea043] hover:bg-[#3fb950] active:scale-95 text-white font-semibold px-3 py-1 rounded transition-all text-[11px] flex items-center gap-1 shadow">
                            <span>💾</span> Submit
                        </button>
                    </div>
                </div>
                
                <!-- Container Monaco Editor -->
                <div class="relative flex-1 w-full h-full overflow-hidden">
                    <div id="monaco-editor-container" class="absolute inset-0"></div>
                </div>
            </div>

            <!-- TERMINAL BAWAH (Collapsible - Tersembunyi Default) -->
            <div id="bottom-terminal-panel" class="hidden h-48 bg-[#252526] border-t border-[#333333] flex flex-col text-xs z-20">
                <!-- Terminal Header Bar -->
                <div class="bg-[#2d2d2d] px-4 py-1.5 border-b border-[#333333] flex justify-between items-center text-[11px] text-[#cccccc]">
                    <div class="flex items-center gap-4 font-semibold">
                        <span class="text-[#4ec9b0] uppercase tracking-wider">Terminal Output</span>
                        <span id="terminal-status" class="text-[10px] text-[#858585]">Status: Idle</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" onclick="clearTerminal()" class="text-[#858585] hover:text-[#cccccc] px-1.5 py-0.5 rounded text-[10px]">Clear</button>
                        <button type="button" onclick="toggleTerminal()" class="text-[#858585] hover:text-[#ffffff] px-1.5 py-0.5 rounded font-bold">✕</button>
                    </div>
                </div>

                <!-- Terminal Body Content -->
                <div class="flex-1 p-3 bg-[#1e1e1e] font-mono text-xs overflow-y-auto flex flex-col justify-between">
                    <div id="terminal-output" class="space-y-1 pr-1 text-[#4ec9b0]">
                        <p class="text-[#858585]">// Terminal Output Sandbox</p>
                        <p class="text-[#cccccc]">> System Ready...</p>
                    </div>

                    <!-- Input Stdin & Execute Button di Bawah Terminal -->
                    <div class="pt-2 mt-2 border-t border-[#333333] flex items-center gap-2">
                        <input type="text" id="stdin-input" placeholder="Input variabel / stdin (opsional)..." class="flex-1 bg-[#2d2d2d] text-[#cccccc] border border-[#3c3c3c] rounded px-2.5 py-1 text-xs focus:outline-none focus:border-[#007acc] transition-all">
                        <button type="button" onclick="runCodeAdvanced()" id="btn-run" class="text-[#cccccc] bg-[#333333] hover:bg-[#3c3c3c] active:scale-95 px-3 py-1 rounded text-[11px] transition-all border border-[#454545]">
                            Execute
                        </button>
                    </div>
                </div>
            </div>

        </div>

    </form>

    <!-- SCRIPT UTAMA: TIMER, KEYSTROKE, AI PROCTORING & COMPILER -->
    <script>
        // --- 1. SCRIPT TIMER AMAN BERBASIS DATABASE ---
        let totalSeconds = <?= (int) $remaining_seconds ?>;
        const timerDisplay = document.getElementById('countdown-timer');

        function updateTimer() {
            if (totalSeconds <= 0) {
                timerDisplay.textContent = "00:00:00";
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
        setInterval(updateTimer, 1000);
        updateTimer();

        // --- 1.1 KEYSTROKE DYNAMICS ---
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

        // --- 1.2 AI PROCTORING & WEBCAM RECORDER ---
        let mediaRecorder;
        let recordedChunks = [];
        let isRecordingClip = false;
        let cocoModel = null;
        let isProctoringActive = false;
        let currentViolationType = "Unknown Violation";

        window.addEventListener('DOMContentLoaded', async () => {
            try {
                if (typeof tf === 'undefined' || typeof cocoSsd === 'undefined') {
                    console.warn("Library AI Proctoring tidak tersedia.");
                    return;
                }
                const stream = await navigator.mediaDevices.getUserMedia({ video: { width: 640, height: 480 }, audio: false });
                
                const hiddenVideo = document.getElementById('proctoring-video');
                hiddenVideo.srcObject = stream;
                hiddenVideo.play();

                const previewVideo = document.getElementById('webcam-preview-video');
                previewVideo.srcObject = stream;
                previewVideo.play();

                setupMediaRecorder(stream);

                cocoModel = await cocoSsd.load();
                document.getElementById('ai-status-badge').classList.remove('hidden');
                isProctoringActive = true;

                setInterval(runAIProctoringLoop, 1500);
            } catch (err) {
                console.warn("Kamera tidak diizinkan atau tidak tersedia:", err);
            }
        });

        function setupMediaRecorder(stream) {
            try {
                mediaRecorder = new MediaRecorder(stream, { mimeType: 'video/webm; codecs=vp8' });
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
                console.error("AI Loop error:", e);
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
            }).catch(error => console.error("Gagal mengunggah klip video:", error));
        }

        // --- 2. MONACO EDITOR & MULTI-FILE ENGINE ---
        let editor = null;
        const extensionMap = {
            'py': 'python', 'rb': 'ruby', 'js': 'javascript', 
            'php': 'php', 'java': 'java', 'cpp': 'cpp', 'c': 'c', 'go': 'go'
        };

        const templates = {
            python: 'print("Hello World")',
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

        function getEditorValue() {
            if (editor !== null && typeof editor.getValue === 'function') {
                return editor.getValue();
            }
            const ta = document.getElementById('fallback-ta');
            return ta ? ta.value : (fileStorage[currentActiveFile] || '');
        }

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
                    fontSize: 13,
                    fontFamily: 'JetBrains Mono, monospace',
                    minimap: { enabled: true },
                    scrollBeyondLastLine: false
                });

                editor.onDidChangeModelContent(function() {
                    fileStorage[currentActiveFile] = editor.getValue();
                });

                setTimeout(() => { if (editor) editor.layout(); }, 100);
                setTimeout(() => { if (editor) editor.layout(); }, 300);
                setTimeout(() => { if (editor) editor.layout(); }, 600);

                window.addEventListener('resize', () => {
                    if (editor) editor.layout();
                });
            }, function(err) {
                activateFallbackTextarea();
            });
        } catch (e) {
            activateFallbackTextarea();
        }

        function activateFallbackTextarea() {
            const container = document.getElementById('monaco-editor-container');
            container.innerHTML = `<textarea id="fallback-ta" class="w-full h-full bg-[#1e1e1e] text-[#d4d4d4] p-4 font-mono text-sm focus:outline-none resize-none"></textarea>`;
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
                el.className = "text-[#858585] hover:bg-[#2a2d2e] hover:text-[#cccccc] px-2.5 py-1 rounded flex items-center justify-between cursor-pointer transition-all group";
            });
            let activeEl = document.getElementById('file-' + filename);
            if (activeEl) {
                activeEl.className = "text-[#ffffff] bg-[#37373d] px-2.5 py-1 rounded flex items-center justify-between cursor-pointer transition-all group";
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
                setTimeout(() => { if (editor) editor.layout(); }, 50);
            } else {
                setEditorValue(fileStorage[filename]);
            }
        }

        function addNewFilePrompt() {
            let fileName = prompt("Masukkan nama file baru (contoh: Main.java, helper.py):");
            if (fileName && fileName.trim() !== "") {
                let cleanName = fileName.trim();
                
                if (fileStorage[cleanName] !== undefined) {
                    alert("File dengan nama tersebut sudah ada!");
                    switchFile(cleanName);
                    return;
                }

                let detectedLang = detectLanguage(cleanName);
                fileStorage[cleanName] = templates[detectedLang] || '// Tulis kode Anda di sini';

                const container = document.getElementById('file-list-container');
                let newFileDiv = document.createElement('div');
                newFileDiv.id = 'file-' + cleanName;
                newFileDiv.className = "text-[#858585] hover:bg-[#2a2d2e] hover:text-[#cccccc] px-2.5 py-1 rounded flex items-center justify-between cursor-pointer transition-all group";
                
                newFileDiv.innerHTML = `
                    <div class="flex items-center gap-2 truncate"><span>📄</span> <span class="file-item-name truncate">${cleanName}</span></div>
                    <button type="button" onclick="deleteFile(event, '${cleanName}')" class="opacity-0 group-hover:opacity-100 text-[#858585] hover:text-[#f48771] px-1 rounded text-[10px]" title="Hapus File">🗑</button>
                `;
                
                newFileDiv.onclick = function() { switchFile(cleanName); };
                container.appendChild(newFileDiv);
                
                switchFile(cleanName);
            }
        }

        function deleteFile(event, filename) {
            event.stopPropagation();

            if (confirm(`Apakah Anda yakin ingin menghapus file "${filename}"?`)) {
                delete fileStorage[filename];

                let fileElement = document.getElementById('file-' + filename);
                if (fileElement) {
                    fileElement.remove();
                }

                if (currentActiveFile === filename) {
                    let remainingFiles = Object.keys(fileStorage);
                    if (remainingFiles.length > 0) {
                        switchFile(remainingFiles[0]);
                    } else {
                        fileStorage['solution.py'] = '';
                        switchFile('solution.py');
                    }
                }
            }
        }

        // --- 3. TERMINAL TOGGLE & COMPILER EMULATOR ---
        function toggleTerminal() {
            const terminalPanel = document.getElementById('bottom-terminal-panel');
            terminalPanel.classList.toggle('hidden');
            setTimeout(() => {
                if (editor) editor.layout();
            }, 100);
        }

        function clearTerminal() {
            document.getElementById('terminal-output').innerHTML = `<p class="text-[#858585]">// Terminal cleared</p>`;
        }

        function runCodeAdvanced() {
            const terminalPanel = document.getElementById('bottom-terminal-panel');
            if (terminalPanel.classList.contains('hidden')) {
                terminalPanel.classList.remove('hidden');
            }
            setTimeout(() => { if (editor) editor.layout(); }, 100);

            let code = getEditorValue();
            let lang = document.getElementById('language-hidden').value;
            let stdinVal = document.getElementById('stdin-input').value;
            
            const terminalOutput = document.getElementById('terminal-output');
            const terminalStatus = document.getElementById('terminal-status');

            terminalOutput.innerHTML = `<p class="text-[#858585]">// Executing ${lang.toUpperCase()}...</p>`;
            terminalStatus.textContent = "Status: Running...";

            if (code.trim() === "") {
                terminalOutput.innerHTML += `<p class="text-[#ce9178]">> Warning: Kode program masih kosong!</p>`;
                terminalStatus.textContent = "Status: Warning";
                return;
            }

            setTimeout(() => {
                let logs = [];
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
                        if (!executed) logs.push("[Python Process Completed - No output printed]");
                    } 
                    else if (lang === 'javascript') {
                        const originalLog = console.log;
                        console.log = function(...args) {
                            logs.push(args.map(a => (typeof a === 'object' ? JSON.stringify(a) : a)).join(' '));
                        };
                        let result = eval(code);
                        if (logs.length === 0 && result !== undefined) logs.push(result);
                        console.log = originalLog;
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
                    } 
                    else {
                        logs.push(`[${lang.toUpperCase()} Simulator]: Output berhasil diproses.`);
                        if (code.includes('cout') || code.includes('printf') || code.includes('println')) {
                            logs.push("Hello World");
                        }
                    }

                    terminalOutput.innerHTML = "";
                    logs.forEach(log => {
                        terminalOutput.innerHTML += `<p class="text-[#4ec9b0]">> ${log}</p>`;
                    });

                    if (stdinVal.trim() !== "") {
                        terminalOutput.innerHTML += `<p class="text-[#858585]">> Stdin Input Used: ${stdinVal}</p>`;
                    }

                    terminalOutput.innerHTML += `<p class="text-[#9cdcfe] font-semibold">> [Process Completed Successfully]</p>`;
                    terminalStatus.textContent = "Status: Success";

                } catch (error) {
                    terminalOutput.innerHTML += `<p class="text-[#f48771] font-semibold">> Syntax Error: ${error.message}</p>`;
                    terminalStatus.textContent = "Status: Error";
                }
                terminalOutput.scrollTop = terminalOutput.scrollHeight;
            }, 300);
        }

        function toggleAndRunCode() {
            runCodeAdvanced();
        }

        function prepareSubmit() {
            let codeVal = getEditorValue();
            if (confirm('Yakin ingin mengirimkan jawaban ujian ini?')) {
                isFinishing = true;
                document.getElementById('hidden_code_input').value = codeVal;
                document.getElementById('flight-time-input').value = JSON.stringify(keystrokeLogs);
                return true;
            }
            return false;
        }

        // --- 4. PROTEKSI ANTI-KELUAR-TAB ---
        const ROOM_ID      = <?= json_encode($room_id) ?>;
        const CSRF_TOKEN   = <?= json_encode($_SESSION['csrf_token']) ?>;
        const VIOLATION_URL = '../../controllers/student/report_violation.php';
        const LEAVE_URL    = '../../controllers/student/leave_exam.php';

        let violationCount = 0;
        let examStarted    = false;
        let isFinishing    = false;

        const overlay  = document.getElementById('start-overlay');
        const banner   = document.getElementById('violation-banner');
        const bannerText = document.getElementById('violation-text');
        const countEl  = document.getElementById('violation-count');

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
                console.warn('Fullscreen ditolak/tidak didukung.', e);
            }
            overlay.classList.add('hidden');
            examStarted = true;
        });

        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
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
    </script>
</body>
</html>