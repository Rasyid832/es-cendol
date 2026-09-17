<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../config/db.php'; // $pdo (PDO)

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'student') {
    header("Location: login.php");
    exit();
}

$user_id         = $_SESSION['user_id'] ?? null;
$user_email      = $_SESSION['email'] ?? 'SISWA';
$user_name       = $_SESSION['name'] ?? 'User Coding';
$identity_number = $_SESSION['identity_number'] ?? 'DEV-001';

$room_id = isset($_GET['room_id']) ? intval($_GET['room_id']) : ($_SESSION['room_id'] ?? 1);

$project_title       = "Workspace Project";
$duration_minutes    = 90;
$project_description = "Tulis dan kembangkan kode program Anda di sini.";
$question_text       = "// Tulis solusi koding Anda di sini\nprint('Hello World');";

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
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">

    <script src="https://unpkg.com/monaco-editor@0.33.0/min/vs/loader.js"></script>
    <link rel="stylesheet" data-name="vs/editor/editor.main" href="https://unpkg.com/monaco-editor@0.33.0/min/vs/editor/editor.main.css">

    <script src="https://cdn.jsdelivr.net/npm/@tensorflow/tfjs@3.18.0/dist/tf.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@tensorflow-models/coco-ssd"></script>

    <script src="https://unpkg.com/peerjs@1.5.2/dist/peerjs.min.js"></script>

    <style>
        body { font-family: 'Inter', sans-serif; background-color: #1e1e1e; color: #d4d4d4; }
        .font-mono { font-family: 'JetBrains Mono', monospace; }
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: #1e1e1e; }
        ::-webkit-scrollbar-thumb { background: #424242; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #4f4f4f; }
            #peer-status { display: none !important; }
    </style>
</head>
<body class="h-screen flex flex-col overflow-hidden select-none bg-[#1e1e1e] text-[#d4d4d4]">

    <div id="start-overlay" class="fixed inset-0 z-50 bg-[#1e1e1e]/90 flex items-center justify-center px-6">
        <div class="bg-[#252526] rounded-lg shadow-2xl p-8 max-w-md w-full text-center border border-[#333333]">
            <h1 class="text-lg font-bold text-[#cccccc] mb-2">Siap Memulai Ujian?</h1>
            <p class="text-xs text-[#858585] mb-6 leading-relaxed">
                Ujian berjalan dalam mode layar penuh dengan pengawasan AI, webcam, dan share screen wajib.
                Berpindah tab, keluar fullscreen, menutup halaman, atau menghentikan share screen akan dicatat sebagai pelanggaran.
                Akumulasi <span class="text-[#f48771] font-semibold">3 kali</span> pelanggaran akan membuat ujian otomatis <span class="text-[#f48771] font-semibold">gugur</span>.
            </p>
            <p id="start-error" class="hidden text-[10px] text-[#f48771] mb-3"></p>
            <button id="start-btn" class="bg-[#0e639c] hover:bg-[#1177bb] active:scale-95 text-white font-medium px-6 py-2.5 rounded text-xs transition-all shadow-md">
                Mulai Ujian (Fullscreen + Share Screen)
            </button>
        </div>
    </div>

    <div id="violation-banner" class="hidden fixed top-0 left-0 right-0 z-40 bg-[#f48771] text-black text-xs font-bold text-center py-2 px-4 shadow-md">
        <span id="violation-text">Pelanggaran terdeteksi.</span>
        <span id="violation-count-wrap"> (Pelanggaran ke-<span id="violation-count">0</span> dari 3)</span>
    </div>

    <video id="proctoring-video" autoplay playsinline muted style="display:none;"></video>

    <div id="webcam-box" class="fixed bottom-4 right-4 z-30 w-36 rounded overflow-hidden border border-[#454545] shadow-2xl bg-black">
        <video id="webcam-preview-video" autoplay muted playsinline class="w-full h-auto block"></video>
        <div id="webcam-status" class="absolute top-1 left-1 flex items-center gap-1 bg-black/70 px-1.5 py-0.5 rounded text-[9px] text-white font-medium">
            <span class="w-1.5 h-1.5 rounded-full bg-red-500 animate-pulse"></span> LIVE
        </div>
    </div>

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

    <form id="code-form" action="../../controllers/student/finish_exam.php" method="POST" class="flex-1 grid grid-cols-1 lg:grid-cols-4 gap-0 overflow-hidden bg-[#1e1e1e]">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
        <input type="hidden" name="room_id" value="<?= htmlspecialchars($room_id) ?>">
        <input type="hidden" name="language" id="language-hidden" value="python">
        <input type="hidden" name="answer_code" id="hidden_code_input">
        <input type="hidden" name="flight_time_data" id="flight-time-input">

        <div class="bg-[#252526] border-r border-[#333333] flex flex-col justify-between overflow-y-auto text-xs">
            <div>
                <div class="px-4 py-2 text-[11px] font-bold text-[#bbbbbb] tracking-wider uppercase border-b border-[#333333] flex justify-between items-center bg-[#2d2d2d]">
                    <span>EXPLORER</span>
                    <button type="button" onclick="addNewFilePrompt()" class="text-[#4ec9b0] hover:text-white font-bold bg-[#333333] hover:bg-[#3c3c3c] px-2 py-0.5 rounded transition-all text-[10px]">+ New File</button>
                </div>

                <div class="p-2 space-y-0.5 border-b border-[#333333] text-[#cccccc]" id="file-list-container">
                    <div id="file-solution.py" class="text-[#ffffff] bg-[#37373d] px-2.5 py-1 rounded flex items-center justify-between cursor-pointer transition-all group" onclick="switchFile('solution.py')">
                        <div class="flex items-center gap-2 truncate"><span>📄</span> <span class="file-item-name truncate">solution.py</span></div>
                        <button type="button" onclick="deleteFile(event, 'solution.py')" class="opacity-0 group-hover:opacity-100 text-[#858585] hover:text-[#f48771] px-1 rounded text-[10px]">🗑</button>
                    </div>
                    <div id="file-Main.java" class="text-[#858585] hover:bg-[#2a2d2e] hover:text-[#cccccc] px-2.5 py-1 rounded flex items-center justify-between cursor-pointer transition-all group" onclick="switchFile('Main.java')">
                        <div class="flex items-center gap-2 truncate"><span>📄</span> <span class="file-item-name truncate">Main.java</span></div>
                        <button type="button" onclick="deleteFile(event, 'Main.java')" class="opacity-0 group-hover:opacity-100 text-[#858585] hover:text-[#f48771] px-1 rounded text-[10px]">🗑</button>
                    </div>
                    <div id="file-script.js" class="text-[#858585] hover:bg-[#2a2d2e] hover:text-[#cccccc] px-2.5 py-1 rounded flex items-center justify-between cursor-pointer transition-all group" onclick="switchFile('script.js')">
                        <div class="flex items-center gap-2 truncate"><span>📄</span> <span class="file-item-name truncate">script.js</span></div>
                        <button type="button" onclick="deleteFile(event, 'script.js')" class="opacity-0 group-hover:opacity-100 text-[#858585] hover:text-[#f48771] px-1 rounded text-[10px]">🗑</button>
                    </div>
                </div>

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

        <div class="lg:col-span-3 flex flex-col h-full overflow-hidden bg-[#1e1e1e]">
            <div class="flex-1 flex flex-col overflow-hidden relative">
                <div class="bg-[#2d2d2d] px-3 py-1.5 border-b border-[#333333] flex justify-between items-center text-xs">
                    <div class="flex items-center gap-3">
                        <span class="font-semibold text-[#cccccc] flex items-center gap-2">
                            <span id="active-filename" class="text-[#ffffff]">solution.py</span>
                            <span id="detected-lang-badge" class="ml-1 text-[9px] bg-[#007acc] text-white px-1.5 py-0.5 rounded uppercase font-mono">python</span>
                        </span>
                    </div>

                    <div class="flex items-center gap-2">
                        <button type="button" onclick="toggleTerminal()" class="bg-[#333333] hover:bg-[#3c3c3c] text-[#cccccc] px-2.5 py-1 rounded text-[11px] transition-all border border-[#454545] flex items-center gap-1">
                            <span>💻</span> <span id="terminal-toggle-label">Terminal</span>
                        </button>
                        <button type="button" onclick="toggleAndRunCode()" class="bg-[#0e639c] hover:bg-[#1177bb] active:scale-95 text-white font-medium px-3 py-1 rounded text-[11px] transition-all flex items-center gap-1 shadow-sm">
                            ▶ Run Code
                        </button>
                        <button type="submit" onclick="return prepareSubmit()" class="bg-[#2ea043] hover:bg-[#3fb950] active:scale-95 text-white font-semibold px-3 py-1 rounded transition-all text-[11px] flex items-center gap-1 shadow">
                            <span>💾</span> Submit
                        </button>
                    </div>
                </div>

                <div class="relative flex-1 w-full h-full overflow-hidden">
                    <div id="monaco-editor-container" class="absolute inset-0"></div>
                </div>
            </div>

            <div id="bottom-terminal-panel" class="hidden h-48 bg-[#252526] border-t border-[#333333] flex flex-col text-xs z-20">
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

                <div class="flex-1 p-3 bg-[#1e1e1e] font-mono text-xs overflow-y-auto flex flex-col justify-between">
                    <div id="terminal-output" class="space-y-1 pr-1 text-[#4ec9b0]">
                        <p class="text-[#858585]">// Terminal Output Sandbox</p>
                        <p class="text-[#cccccc]">> System Ready...</p>
                    </div>

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

    <script>
        const STUDENT_ID = <?= json_encode((string)$user_id) ?>;
        const ROOM_ID     = <?= json_encode((string)$room_id) ?>;
        const CSRF_TOKEN  = <?= json_encode($_SESSION['csrf_token']) ?>;
        const SUPERVISOR_PEER_ID = `codeprocess-supervisor-${STUDENT_ID}-${ROOM_ID}`;
        const VIOLATION_URL = '../../controllers/student/report_violation.php';
        const LEAVE_URL    = '../../controllers/student/leave_exam.php';

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

        let studentPeer = null;
        let supervisorConn = null;
        let camStreamGlobal = null;
        let screenStreamGlobal = null;
        let camCallInstance = null;
        let screenCallInstance = null;
        let peerConnecting = false;
        let lastSupervisorPong = 0;
        function setPeerStatus(text, ok = false) {
            console.log('[PeerJS]', text);
        }

        function destroyStudentPeer() {
            try {
                if (supervisorConn) supervisorConn.close();
                if (camCallInstance) camCallInstance.close();
                if (screenCallInstance) screenCallInstance.close();
                if (studentPeer && !studentPeer.destroyed) studentPeer.destroy();
            } catch (e) { console.warn('Gagal membersihkan Peer:', e); }
            supervisorConn = null;
            camCallInstance = null;
            screenCallInstance = null;
            studentPeer = null;
        }

        function initPeerConnection() {
            if (studentPeer && !studentPeer.destroyed) return;
            if (peerConnecting) return;
            peerConnecting = true;
            setPeerStatus('Menghubungkan ke pengawas...', false);

            studentPeer = new Peer();
            studentPeerConnectionHandlerInstalled = false;
            installIncomingSupervisorConnectionHandler();

            studentPeer.on('open', () => {
                peerConnecting = false;
                console.log('Student Peer siap:', studentPeer.id);
                connectToSupervisor();
            });

            studentPeer.on('error', (err) => {
                peerConnecting = false;
                console.warn('Peer error:', err.type, err);
                setPeerStatus(err.type === 'peer-unavailable'
                    ? 'Pengawas belum membuka halaman...'
                    : 'PeerJS error: ' + err.type, false);

                if (err.type !== 'peer-unavailable' && err.type !== 'webrtc') {
                    try { if (studentPeer && !studentPeer.destroyed) studentPeer.destroy(); } catch (e) {}
                    studentPeer = null;
                }
            });

            studentPeer.on('disconnected', () => {
                setPeerStatus('Koneksi terputus, menyambung ulang...', false);
                setTimeout(() => {
                    if (studentPeer && !studentPeer.destroyed) {
                        try { studentPeer.reconnect(); }
                        catch (e) { destroyStudentPeer(); initPeerConnection(); }
                    }
                }, 1500);
            });
        }

        function bindSupervisorConnection(conn) {
            if (!conn) return;

            if (supervisorConn && supervisorConn !== conn) {
                try { supervisorConn.close(); } catch (e) {}
            }
            supervisorConn = conn;
            lastSupervisorPong = Date.now();

            conn.on('open', () => {
                console.log('DataChannel ke pengawas TERHUBUNG:', conn.peer);
                if (supervisorConn === conn) {
                    lastSupervisorPong = Date.now();
                    setPeerStatus('Terhubung ke pengawas', true);
                    try { conn.send({ type: 'STREAM_READY' }); } catch (e) {}
                    setTimeout(sendStreamsToSupervisor, 150);
                }
            });

            conn.on('data', handleSupervisorMessage);
            conn.on('close', () => {
                if (supervisorConn === conn) {
                    supervisorConn = null;
                    lastSupervisorPong = 0;
                    try { if (camCallInstance) camCallInstance.close(); } catch (e) {}
                    try { if (screenCallInstance) screenCallInstance.close(); } catch (e) {}
                    camCallInstance = null;
                    screenCallInstance = null;
                    setPeerStatus('Menunggu pengawas...', false);
                    setTimeout(connectToSupervisor, 250);
                }
            });
            conn.on('error', (err) => {
                console.warn('Data connection error:', err);
                if (supervisorConn === conn) {
                    try { conn.close(); } catch (e) {}
                    supervisorConn = null;
                    setPeerStatus('Menunggu pengawas...', false);
                }
            });
        }

        function connectToSupervisor() {
            if (!studentPeer || studentPeer.destroyed || !studentPeer.open) return;
            if (supervisorConn && supervisorConn.open) return;

            if (supervisorConn) { try { supervisorConn.close(); } catch (e) {} }

            const conn = studentPeer.connect(SUPERVISOR_PEER_ID, {
                reliable: true,
                serialization: 'json'
            });
            bindSupervisorConnection(conn);
        }

        let studentPeerConnectionHandlerInstalled = false;
        function installIncomingSupervisorConnectionHandler() {
            if (!studentPeer || studentPeerConnectionHandlerInstalled) return;
            studentPeerConnectionHandlerInstalled = true;
            studentPeer.on('connection', (conn) => {
                console.log('Koneksi DATA masuk dari pengawas:', conn.peer);
                if (conn.peer === SUPERVISOR_PEER_ID) {
                    bindSupervisorConnection(conn);
                } else {
                    try { conn.close(); } catch (e) {}
                }
            });
        }

        function sendStreamsToSupervisor() {
            if (!studentPeer || studentPeer.destroyed || !studentPeer.open) return;
            if (!supervisorConn || !supervisorConn.open) return;

            if (camCallInstance && camCallInstance.open === false && camCallInstance.__startedAt) {
                if (Date.now() - camCallInstance.__startedAt > 4000) {
                    try { camCallInstance.close(); } catch (e) {}
                    camCallInstance = null;
                }
            }
            if (screenCallInstance && screenCallInstance.open === false && screenCallInstance.__startedAt) {
                if (Date.now() - screenCallInstance.__startedAt > 4000) {
                    try { screenCallInstance.close(); } catch (e) {}
                    screenCallInstance = null;
                }
            }

            if (camStreamGlobal && !camCallInstance) {
                try {
                    const call = studentPeer.call(SUPERVISOR_PEER_ID, camStreamGlobal, {
                        metadata: { type: 'cam', studentId: STUDENT_ID, roomId: ROOM_ID }
                    });
                    if (!call) throw new Error('PeerJS tidak mengembalikan MediaConnection kamera');
                    call.__startedAt = Date.now();
                    camCallInstance = call;
                    setPeerStatus('Mengirim kamera...', true);

                    call.on('close', () => {
                        if (camCallInstance === call) camCallInstance = null;
                    });
                    call.on('error', (err) => {
                        console.error('Camera call error:', err);
                        if (camCallInstance === call) camCallInstance = null;
                    });
                } catch (e) {
                    console.error('Call kamera gagal:', e);
                    camCallInstance = null;
                }
            }

            if (screenStreamGlobal && !screenCallInstance) {
                const track = screenStreamGlobal.getVideoTracks()[0];
                if (!track || track.readyState !== 'live') {
                    screenStreamGlobal = null;
                    return;
                }
                try {
                    const call = studentPeer.call(SUPERVISOR_PEER_ID, screenStreamGlobal, {
                        metadata: { type: 'screen', studentId: STUDENT_ID, roomId: ROOM_ID }
                    });
                    if (!call) throw new Error('PeerJS tidak mengembalikan MediaConnection screen');
                    call.__startedAt = Date.now();
                    screenCallInstance = call;
                    setPeerStatus('Mengirim screen share...', true);

                    call.on('close', () => {
                        if (screenCallInstance === call) screenCallInstance = null;
                    });
                    call.on('error', (err) => {
                        console.error('Screen call error:', err);
                        if (screenCallInstance === call) screenCallInstance = null;
                    });
                } catch (e) {
                    console.error('Call screen gagal:', e);
                    screenCallInstance = null;
                }
            }

            setTimeout(() => {
                if (camCallInstance && camCallInstance.__startedAt && !camCallInstance.open && Date.now() - camCallInstance.__startedAt > 4000) {
                    try { camCallInstance.close(); } catch (e) {}
                    camCallInstance = null;
                }
                if (screenCallInstance && screenCallInstance.__startedAt && !screenCallInstance.open && Date.now() - screenCallInstance.__startedAt > 4000) {
                    try { screenCallInstance.close(); } catch (e) {}
                    screenCallInstance = null;
                }
            }, 4500);
        }

        setInterval(() => {
            if (!examStarted || isFinishing) return;

            if (supervisorConn && supervisorConn.open) {
                try { supervisorConn.send({ type: 'PING' }); } catch (e) {}
                if (lastSupervisorPong && Date.now() - lastSupervisorPong > 3500) {
                    try { supervisorConn.close(); } catch (e) {}
                    supervisorConn = null;
                    try { if (camCallInstance) camCallInstance.close(); } catch (e) {}
                    try { if (screenCallInstance) screenCallInstance.close(); } catch (e) {}
                    camCallInstance = null;
                    screenCallInstance = null;
                }
            }

            connectToSupervisor();

            if (studentPeer && studentPeer.open && supervisorConn && supervisorConn.open) {
                setTimeout(sendStreamsToSupervisor, 100);
            }
        }, 1000);

        function handleSupervisorMessage(data) {
            if (!data || !data.type) return;

            if (data.type === 'PONG') {
                lastSupervisorPong = Date.now();
                return;
            }

            if (data.type === 'REQUEST_STREAMS' || data.type === 'STREAM_READY') {
                try { if (camCallInstance) camCallInstance.close(); } catch (e) {}
                try { if (screenCallInstance) screenCallInstance.close(); } catch (e) {}
                camCallInstance = null;
                screenCallInstance = null;
                setTimeout(sendStreamsToSupervisor, 100);
                setTimeout(sendStreamsToSupervisor, 700);
                setTimeout(sendStreamsToSupervisor, 1600);
                return;
            }

            if (data.type === 'WARNING') {
                showBanner(data.message || 'Peringatan dari pengawas!');
            } else if (data.type === 'STOP_EXAM') {
                isFinishing = true;
                alert('Ujian Anda dihentikan oleh pengawas.');
                window.location.href = '../student/dashboard.php';
            }
        }

        async function initCamera() {
            try {
                const stream = await navigator.mediaDevices.getUserMedia({
                    video: { width: { ideal: 640 }, height: { ideal: 480 }, frameRate: { max: 15 } },
                    audio: false
                });
                camStreamGlobal = stream;

                const hiddenVideo = document.getElementById('proctoring-video');
                if (hiddenVideo) {
                    hiddenVideo.srcObject = stream;
                    hiddenVideo.play().catch(() => {});
                }

                const previewVideo = document.getElementById('webcam-preview-video');
                if (previewVideo) {
                    previewVideo.srcObject = stream;
                    previewVideo.play().catch(() => {});
                }

                setupMediaRecorder(stream);
                connectToSupervisor();
                return true;
            } catch (err) {
                console.warn('Kamera ditolak/tidak tersedia:', err);
                return false;
            }
        }

        async function startScreenShare() {
            try {
                if (!navigator.mediaDevices || !navigator.mediaDevices.getDisplayMedia) {
                    throw new Error('Browser tidak mendukung screen sharing.');
                }

                const stream = await navigator.mediaDevices.getDisplayMedia({
                    video: {
                        displaySurface: 'monitor',
                        cursor: 'always',
                        frameRate: { max: 15 }
                    },
                    audio: false
                });

                const videoTrack = stream.getVideoTracks()[0];
                if (!videoTrack) {
                    stream.getTracks().forEach(t => t.stop());
                    return false;
                }

                const settings = videoTrack.getSettings();
                if (settings.displaySurface && settings.displaySurface !== 'monitor') {
                    stream.getTracks().forEach(t => t.stop());
                    alert("DITOLAK! Anda WAJIB membagikan 'Seluruh Layar' (Entire Screen), bukan Window atau Tab Browser.");
                    return false;
                }

                screenStreamGlobal = stream;

                videoTrack.addEventListener('ended', () => {
                    if (screenCallInstance) {
                        try { screenCallInstance.close(); } catch (e) {}
                        screenCallInstance = null;
                    }
                    screenStreamGlobal = null;
                    if (examStarted && !isFinishing) {
                        reportViolation('berhenti membagikan layar (screen share)');
                    }
                });

                connectToSupervisor();
                return true;
            } catch (err) {
                console.warn('Screen share ditolak/tidak didukung:', err);
                return false;
            }
        }

        let mediaRecorder;
        let recordedChunks = [];
        let isRecordingClip = false;
        let cocoModel = null;
        let isProctoringActive = false;
        let currentViolationType = "Unknown Violation";

        window.addEventListener('DOMContentLoaded', async () => {
            const camReady = await initCamera();
            if (!camReady) {
                document.getElementById('start-error').textContent = "Kamera wajib diaktifkan untuk memulai ujian.";
                document.getElementById('start-error').classList.remove('hidden');
            }

            if (typeof cocoSsd !== 'undefined') {
                try {
                    cocoModel = await cocoSsd.load();
                    document.getElementById('ai-status-badge').classList.remove('hidden');
                    isProctoringActive = true;
                    setInterval(runAIProctoringLoop, 1500);
                } catch(e) { console.warn("Gagal memuat COCO-SSD", e); }
            }
        });

        function setupMediaRecorder(stream) {
            try {
                mediaRecorder = new MediaRecorder(stream, { mimeType: 'video/webm; codecs=vp8' });
            } catch (e) {
                try {
                    mediaRecorder = new MediaRecorder(stream);
                } catch(err) { return; }
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
                    if (mediaRecorder && mediaRecorder.state === "recording") {
                        mediaRecorder.stop();
                    }
                }, 5000);
            }
        }

        function uploadViolationClip(videoBlob, violationType) {
            const formData = new FormData();
            formData.append('room_id', ROOM_ID);
            formData.append('violation_type', violationType);
            formData.append('csrf_token', CSRF_TOKEN);
            formData.append('video_file', videoBlob, 'violation_evidence.webm');
            formData.append('timestamp', new Date().toISOString());

            fetch('../../controllers/student/report_violation.php', {
                method: 'POST',
                body: formData
            }).catch(error => console.error("Gagal mengunggah klip video:", error));
        }

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
            const errEl = document.getElementById('start-error');
            errEl.classList.add('hidden');

            if (!camStreamGlobal) {
                const camRetry = await initCamera();
                if (!camRetry) {
                    errEl.textContent = "Kamera belum aktif. Izinkan akses kamera lalu muat ulang halaman.";
                    errEl.classList.remove('hidden');
                    return;
                }
            }

            const shareOk = await startScreenShare();
            if (!shareOk) {
                errEl.textContent = "Anda harus mengizinkan Share Screen (WAJIB pilih 'Seluruh Layar') untuk memulai ujian.";
                errEl.classList.remove('hidden');
                return;
            }

            try {
                await document.documentElement.requestFullscreen();
            } catch (e) {
                console.warn('Fullscreen ditolak/tidak didukung.', e);
            }

            overlay.classList.add('hidden');
            examStarted = true;

            initPeerConnection();
            setTimeout(connectToSupervisor, 500);
        });

        let exitViolationPending = false;
        let exitViolationSent = false;
        let lastExitViolationAt = 0;

        function reportViolationBeacon(reason) {
            if (!examStarted || isFinishing || exitViolationSent) return;

            const now = Date.now();
            if (now - lastExitViolationAt < 2000) return;
            lastExitViolationAt = now;
            exitViolationSent = true;

            const payload = new URLSearchParams({
                room_id: ROOM_ID,
                csrf_token: CSRF_TOKEN,
                reason: reason
            }).toString();

            try {
                navigator.sendBeacon(
                    VIOLATION_URL,
                    new Blob([payload], { type: 'application/x-www-form-urlencoded' })
                );
            } catch (e) {
                try {
                    fetch(VIOLATION_URL, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: payload,
                        keepalive: true
                    });
                } catch (err) {}
            }
        }

        document.addEventListener('visibilitychange', () => {
            if (!examStarted || isFinishing) return;

            if (document.hidden) {
                exitViolationPending = true;

                setTimeout(() => {
                    if (document.hidden && exitViolationPending && !isFinishing) {
                        reportViolation('pindah tab / minimize');
                    }
                }, 250);
            } else {
                exitViolationPending = false;
                exitViolationSent = false;
            }
        });

        document.addEventListener('fullscreenchange', () => {
            if (!examStarted || isFinishing) return;
            if (!document.fullscreenElement) {
                reportViolation('keluar fullscreen');
            }
        });

        window.addEventListener('beforeunload', (e) => {
            if (!examStarted || isFinishing) return;
            e.preventDefault();
            e.returnValue = '';
        });

        window.addEventListener('pagehide', (e) => {
            if (!examStarted || isFinishing) return;
            if (!e.persisted) {
                reportViolationBeacon('menutup browser / halaman ujian');
            }
        });
    </script>
</body>
</html>