<?php
session_start();
require_once __DIR__ . '/../../config/db.php'; // $pdo (PDO) untuk pelacakan sesi/pelanggaran

// Proteksi: wajib login sebagai student
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'student') {
    header("Location: login.php");
    exit();
}

// Koneksi ke Database (legacy, dipakai untuk exam_rooms & telemetry_logs)
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "codeprocess_db";

$conn = @new mysqli($host, $user, $pass, $dbname);

// Dapatkan Data User dari Session
$user_id = $_SESSION['user_id'] ?? null;
$user_email = $_SESSION['email'] ?? 'SISWA';
$identity_number = $_SESSION['identity_number'] ?? 'GUEST';

// Ambil Data Ujian dari Database jika ada
$room_id = isset($_GET['room_id']) ? intval($_GET['room_id']) : 1;
$exam_title = "Pengerjaan Ujian Coding";
$duration_minutes = 60;

// === GUARD: wajib sudah "join" dulu lewat dashboard, dan sesi harus masih 'ongoing' ===
// Ini yang menutup celah: sebelumnya room_id bisa diketik manual di URL tanpa join,
// dan siswa yang sudah keluar/selesai masih bisa membuka lagi lewat back/history browser.
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

// Token CSRF untuk komunikasi ke controller pelanggaran (report_violation.php / leave_exam.php)
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Ambil data ujian asli dari tabel `rooms` (menggantikan query lama ke
// tabel `exam_rooms` yang tidak pernah ada di database ini)
$question_text = null;
try {
    $stmtRoomData = $pdo->prepare("SELECT subject_name, duration, question_text FROM rooms WHERE id = :id LIMIT 1");
    $stmtRoomData->execute(['id' => $room_id]);
    $roomData = $stmtRoomData->fetch(PDO::FETCH_ASSOC);
    if ($roomData) {
        $exam_title        = $roomData['subject_name'];
        $duration_minutes  = (int) $roomData['duration'];
        $question_text     = $roomData['question_text'];
    }
} catch (PDOException $e) {
    error_log('[LEMBAR ROOM DATA ERROR] ' . $e->getMessage());
}

// Handling Form Submit Kodingan
$message = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_code'])) {
    $submitted_code = $_POST['code_input'] ?? '';
    $language = $_POST['language'] ?? 'javascript';

    if ($user_id && $pdo) {
        try {
            // 1. Simpan kode ke sessions (ini yang dibaca dashboard dosen untuk menilai)
            //    sekaligus tandai sesi SELESAI (bukan gugur)
            $stmtFinish = $pdo->prepare("
                UPDATE sessions
                SET status = 'completed', finished_at = NOW(),
                    submitted_code = :code, submitted_language = :lang
                WHERE room_id = :room_id AND student_id = :student_id AND status = 'ongoing'
            ");
            $stmtFinish->execute([
                'code'       => $submitted_code,
                'lang'       => $language,
                'room_id'    => $room_id,
                'student_id' => $user_id,
            ]);

            // 2. Cari id sesi yang baru saja diselesaikan, untuk dicatat ke telemetry_logs (audit log)
            $stmtSessionId = $pdo->prepare("
                SELECT id FROM sessions
                WHERE room_id = :room_id AND student_id = :student_id
                ORDER BY id DESC LIMIT 1
            ");
            $stmtSessionId->execute(['room_id' => $room_id, 'student_id' => $user_id]);
            $sessRow = $stmtSessionId->fetch(PDO::FETCH_ASSOC);

            if ($sessRow) {
                $json_data = json_encode(['language' => $language, 'code' => $submitted_code]);
                $stmtLog = $pdo->prepare("INSERT INTO telemetry_logs (session_id, flight_time_data) VALUES (:sid, :data)");
                $stmtLog->execute(['sid' => $sessRow['id'], 'data' => $json_data]);
            }

            $message = "<script>alert('Kode berhasil disimpan ke database!');</script>";
        } catch (PDOException $e) {
            error_log('[LEMBAR SUBMIT ERROR] ' . $e->getMessage());
            $message = "<script>alert('Gagal menyimpan ke database.');</script>";
        }
    } else {
        $message = "<script>alert('Kode berhasil dikirim (Mode Demo / Belum Login)!');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lembar Pengerjaan</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; }
    </style>
    <!-- Object detection: dipakai untuk mendeteksi HP/perangkat lain lewat kamera -->
    <script src="https://cdn.jsdelivr.net/npm/@tensorflow/tfjs@4.20.0/dist/tf.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@tensorflow-models/coco-ssd@2.2.3/dist/coco-ssd.min.js"></script>
</head>
<body class="bg-[#B08D8D] h-screen flex flex-col p-6 overflow-hidden">

    <?php echo $message; ?>

    <!-- OVERLAY: Wajib klik dulu supaya browser mengizinkan Fullscreen API -->
    <div id="start-overlay" class="fixed inset-0 z-50 bg-black/80 flex items-center justify-center px-6">
        <div class="bg-[#E0E0E0] rounded-2xl shadow-2xl p-8 max-w-md w-full text-center">
            <h1 class="text-xl font-bold text-gray-900 mb-2">Siap Memulai Ujian?</h1>
            <p class="text-sm text-gray-600 mb-6">
                Ujian akan berjalan dalam mode layar penuh. Berpindah tab, keluar fullscreen, atau
                menutup halaman ini akan dicatat sebagai pelanggaran. Setelah
                <span class="text-red-600 font-semibold">3 kali</span> pelanggaran, atau jika Anda
                menutup halaman ini, ujian otomatis <span class="text-red-600 font-semibold">gugur</span>
                dan tidak bisa dibuka lagi.
            </p>
            <button id="start-btn" class="bg-red-600 hover:bg-red-700 active:scale-95 text-white font-semibold px-6 py-3 rounded-xl w-full transition-all">
                Mulai Ujian (Fullscreen)
            </button>
        </div>
    </div>

    <!-- WARNING BANNER: muncul saat pelanggaran terdeteksi -->
    <div id="violation-banner" class="hidden fixed top-0 left-0 right-0 z-40 bg-red-600 text-white text-sm font-semibold text-center py-2.5 px-4">
        <span id="violation-text">Pelanggaran terdeteksi.</span>
        <span id="violation-count-wrap"> (<span id="violation-count">0</span>/3)</span>
    </div>

    <!-- KAMERA PENGAWASAN: aktif sepanjang ujian, kecil di pojok kanan bawah -->
    <div id="webcam-box" class="hidden fixed bottom-5 right-5 z-30 w-40 rounded-xl overflow-hidden border-2 border-slate-700 shadow-2xl bg-black">
        <video id="webcam-video" autoplay muted playsinline class="w-full h-auto block"></video>
        <div id="webcam-status" class="absolute top-1 left-1 flex items-center gap-1 bg-black/60 px-1.5 py-0.5 rounded text-[10px] text-white">
            <span class="w-1.5 h-1.5 rounded-full bg-red-500 animate-pulse"></span> LIVE
        </div>
    </div>

    <!-- Container Utama -->
    <div class="bg-[#E0E0E0] rounded-2xl shadow-2xl flex-1 flex flex-col overflow-hidden">

        <!-- HEADER BAR (Nama Siswa & Timer) -->
        <div class="flex justify-between items-center px-8 py-4 border-b border-gray-300">
            <div class="font-bold text-lg tracking-wider text-black">
                NAMA: <span class="font-normal text-gray-800"><?= htmlspecialchars($user_email) ?> (<?= htmlspecialchars($identity_number) ?>)</span>
            </div>

            <div class="font-bold text-lg tracking-wider text-black">
                TIMER <span id="timer-display" class="text-red-600 font-mono ml-2">00:00:00</span>
            </div>
        </div>

        <!-- FORM PENGERJAAN 3 KOLOM -->
        <form id="code-form" method="POST" class="flex-1 grid grid-cols-3 gap-6 p-6 overflow-hidden">
            
            <!-- KOLOM 1: Deskripsi Soal (Kiri) -->
            <div class="bg-white rounded-xl p-6 flex flex-col justify-start overflow-y-auto shadow-sm border border-gray-200">
                <h2 class="text-xl font-bold mb-4 text-gray-800 border-b pb-2"><?= htmlspecialchars($exam_title) ?></h2>
                <div class="text-gray-600 space-y-3 text-sm">
                    <p><strong>Deskripsi Soal:</strong></p>
                    <?php if (!empty($question_text)): ?>
                        <div class="whitespace-pre-line leading-relaxed"><?= nl2br(htmlspecialchars($question_text)) ?></div>
                    <?php else: ?>
                        <p>Pilihlah bahasa pemrograman yang ingin digunakan di atas editor kode. Buatlah program yang mencetak output sesuai spesifikasi.</p>
                        <p><strong>Contoh Perintah Output:</strong></p>
                        <ul class="list-disc list-inside space-y-1 text-xs font-mono bg-gray-50 p-3 rounded-lg border">
                            <li><strong>Python:</strong> <code>print("Hello World")</code></li>
                            <li><strong>PHP:</strong> <code>&lt;?php echo "Hello World";</code></li>
                            <li><strong>C++:</strong> <code>std::cout &lt;&lt; "Hello";</code></li>
                            <li><strong>Java:</strong> <code>System.out.println("Hello");</code></li>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>

            <!-- KOLOM 2: Editor Kodingan (Tengah) -->
            <div class="bg-white rounded-xl flex flex-col overflow-hidden shadow-sm border border-gray-200">
                <div class="bg-gray-100 px-4 py-2 border-b border-gray-200 flex justify-between items-center">
                    <span class="text-xs font-semibold text-gray-500 tracking-wider">CODE EDITOR</span>
                    
                    <!-- Dropdown Pilihan Bahasa Pemrograman -->
                    <select name="language" id="language-select" onchange="changeLanguageTemplate()" class="text-xs bg-white border border-gray-300 rounded px-2 py-1 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                        <option value="python">Python 3</option>
                        <option value="javascript">JavaScript (Node.js)</option>
                        <option value="php">PHP</option>
                        <option value="cpp">C++</option>
                        <option value="java">Java</option>
                        <option value="c">C</option>
                    </select>
                </div>
                
                <textarea 
                    name="code_input" 
                    id="code_input" 
                    class="w-full h-full p-4 font-mono text-sm focus:outline-none resize-none bg-white text-gray-800" 
                    placeholder="Tuliskan kode program di sini..."
                    required
                ></textarea>
            </div>

            <!-- KOLOM 3: Terminal & Tombol Submit (Kanan) -->
            <div class="bg-white rounded-xl p-5 flex flex-col relative justify-between shadow-sm border border-gray-200">
                
                <!-- Tombol Submit Merah -->
                <div class="flex justify-end mb-4">
                    <button 
                        type="submit" 
                        name="submit_code" 
                        class="bg-red-600 hover:bg-red-700 active:scale-95 text-white font-semibold px-8 py-2.5 rounded-xl shadow-md transition-all text-sm tracking-wide"
                    >
                        Submit
                    </button>
                </div>

                <!-- Area Terminal Multi-Language Compiler -->
                <div class="bg-black text-green-400 font-mono text-xs p-4 rounded-xl flex-1 overflow-hidden flex flex-col justify-between border border-gray-800">
                    
                    <!-- Terminal Output Box -->
                    <div id="terminal-output" class="flex-1 overflow-y-auto space-y-1 mb-2 pr-1">
                        <p class="text-gray-500">// Terminal Output</p>
                        <p class="text-gray-400">> System Ready...</p>
                    </div>

                    <!-- Optional Input untuk stdin (Program Interaktif) -->
                    <div class="mb-2">
                        <input type="text" id="stdin-input" placeholder="Input variabel/stdin (opsional)..." class="w-full bg-gray-900 text-gray-300 border border-gray-700 rounded px-2 py-1 text-xs focus:outline-none focus:border-green-500">
                    </div>

                    <!-- Footer Terminal Bar -->
                    <div class="pt-2 border-t border-gray-800 flex justify-between items-center text-gray-400">
                        <span id="terminal-status">Status: Idle</span>
                        <button type="button" onclick="runCodeMultiLanguage()" id="btn-run" class="text-white bg-gray-800 hover:bg-gray-700 active:scale-95 px-4 py-1.5 rounded-lg text-xs font-sans transition-all border border-gray-700 flex items-center gap-1">
                            Run Code
                        </button>
                    </div>
                </div>

            </div>

        </form>

    </div>

    <!-- SCRIPT TIMER & COMPILER ENGINE -->
    <script>
        // --- 1. SCRIPT TIMER ---
        let totalSeconds = <?= $duration_minutes * 60 ?>;
        const timerDisplay = document.getElementById('timer-display');

        function updateTimer() {
            let hours = Math.floor(totalSeconds / 3600);
            let minutes = Math.floor((totalSeconds % 3600) / 60);
            let seconds = totalSeconds % 60;

            hours = hours < 10 ? '0' + hours : hours;
            minutes = minutes < 10 ? '0' + minutes : minutes;
            seconds = seconds < 10 ? '0' + seconds : seconds;

            timerDisplay.textContent = `${hours}:${minutes}:${seconds}`;

            if (totalSeconds > 0) {
                totalSeconds--;
            } else {
                if (typeof isFinishing !== 'undefined') { isFinishing = true; }
                if (typeof stopWebcam === 'function') { stopWebcam(); }
                alert("Waktu habis! Kode Anda disubmit otomatis.");
                document.getElementById('code-form').submit();
            }
        }
        setInterval(updateTimer, 1000);

        // --- 2. TEMPLATE KODE DEFAULT SAAT BAHASA DIUBAH ---
        const templates = {
            python: 'print("Hello World")',
            javascript: 'console.log("Hello World");',
            php: '<?php\necho "Hello World";',
            cpp: '#include <iostream>\n\nint main() {\n    std::cout << "Hello World";\n    return 0;\n}',
            java: 'public class Main {\n    public static void main(String[] args) {\n        System.out.println("Hello World");\n    }\n}',
            c: '#include <stdio.h>\n\nint main() {\n    printf("Hello World");\n    return 0;\n}'
        };

        function changeLanguageTemplate() {
            const lang = document.getElementById('language-select').value;
            document.getElementById('code_input').value = templates[lang] || '';
        }
        
        // Load template default pertama kali
        changeLanguageTemplate();

        // --- RUN CODE MULTI-LANGUAGE (HYBRID ENGINE) ---
function runCodeMultiLanguage() {
    const code = document.getElementById('code_input').value;
    const lang = document.getElementById('language-select').value;
    const stdin = document.getElementById('stdin-input').value;
    
    const terminalOutput = document.getElementById('terminal-output');
    const terminalStatus = document.getElementById('terminal-status');

    terminalOutput.innerHTML = `<p class="text-gray-500">// Terminal Output (${lang.toUpperCase()})</p>`;

    if (code.trim() === "") {
        terminalOutput.innerHTML += `<p class="text-yellow-400">> Warning: Kode program masih kosong!</p>`;
        terminalStatus.textContent = "Status: Warning";
        return;
    }

    terminalStatus.textContent = "Status: Executing...";

    let logs = [];
    
    // 1. EKSEKUSI PYTHON / JAVASCRIPT / PHP LOKAL
    try {
        if (lang === 'python') {
            // Parser Python Sederhana ke Output Terminal
            let lines = code.split('\n');
            let executed = false;

            lines.forEach(line => {
                let trimmed = line.trim();
                // Deteksi print(...) pada Python
                let match = trimmed.match(/^print\((.*)\)$/);
                if (match) {
                    let content = match[1].trim();
                    // Menghilangkan kutip jika string
                    if ((content.startsWith('"') && content.endsWith('"')) || (content.startsWith("'") && content.endsWith("'"))) {
                        content = content.slice(1, -1);
                    } else {
                        // Jika berisi ekspresi matematika/variabel
                        try { content = eval(content); } catch(e) {}
                    }
                    logs.push(content);
                    executed = true;
                }
            });

            if (!executed) {
                logs.push("[Python Process Completed - No output printed]");
            }
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
            // Simulated PHP Engine
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
            // Bahasa C, C++, Java Simulator Output
            logs.push(`[${lang.toUpperCase()} Simulator]: Output berhasil diproses.`);
            if (code.includes('cout') || code.includes('printf') || code.includes('println')) {
                logs.push("Hello World");
            }
        }

        // Tampilkan Hasil ke Terminal
        logs.forEach(log => {
            terminalOutput.innerHTML += `<p class="text-green-400">> ${log}</p>`;
        });

        if (stdin.trim() !== "") {
            terminalOutput.innerHTML += `<p class="text-gray-400">> Stdin Input Used: ${stdin}</p>`;
        }

        terminalOutput.innerHTML += `<p class="text-blue-400 font-semibold">> [Process Completed Successfully]</p>`;
        terminalStatus.textContent = "Status: Success";

    } catch (error) {
        terminalOutput.innerHTML += `<p class="text-red-500 font-semibold">> Syntax Error: ${error.message}</p>`;
        terminalStatus.textContent = "Status: Error";
    }

    terminalOutput.scrollTop = terminalOutput.scrollHeight;
}

        // ===== PROTEKSI ANTI-KELUAR-TAB =====
        const ROOM_ID        = <?= json_encode($room_id) ?>;
        const CSRF_TOKEN     = <?= json_encode($_SESSION['csrf_token']) ?>;
        const VIOLATION_URL  = '../../controllers/student/report_violation.php';
        const LEAVE_URL      = '../../controllers/student/leave_exam.php';

        let violationCount = 0;
        let examStarted     = false;
        let isFinishing      = false; // true saat submit resmi, supaya tidak ikut dianggap "keluar"
        let detectionModel   = null;
        let webcamStream     = null;
        let phoneStrikeCount = 0; // deteksi berturut-turut, biar tidak salah-lapor sekali kelewat

        const overlay      = document.getElementById('start-overlay');
        const banner       = document.getElementById('violation-banner');
        const bannerText   = document.getElementById('violation-text');
        const countEl      = document.getElementById('violation-count');
        const webcamBox     = document.getElementById('webcam-box');
        const webcamVideo   = document.getElementById('webcam-video');
        const webcamStatus  = document.getElementById('webcam-status');

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

        // Preload model deteksi objek di background begitu halaman dibuka,
        // supaya sudah siap saat siswa klik "Mulai Ujian" (tidak perlu nunggu lagi).
        async function loadDetectionModel() {
            try {
                detectionModel = await cocoSsd.load({ base: 'lite_mobilenet_v2' });
                console.log('Model deteksi objek siap.');
            } catch (e) {
                console.warn('Gagal memuat model deteksi objek, fitur deteksi HP dinonaktifkan.', e);
            }
        }
        loadDetectionModel();

        // Aktifkan kamera & tampilkan preview kecil di pojok layar
        async function startWebcam() {
            try {
                webcamStream = await navigator.mediaDevices.getUserMedia({ video: { width: 320, height: 240 }, audio: false });
                webcamVideo.srcObject = webcamStream;
                webcamBox.classList.remove('hidden');

                // Kalau kamera terputus/dicabut/izin dicabut di tengah ujian -> gugurkan
                webcamStream.getVideoTracks()[0].addEventListener('ended', () => {
                    if (examStarted && !isFinishing) {
                        reportViolation('kamera terputus / dimatikan');
                    }
                });

                startPhoneDetectionLoop();
            } catch (e) {
                console.warn('Kamera tidak diizinkan/tidak tersedia.', e);
                alert('Kamera wajib diaktifkan untuk mengikuti ujian ini. Silakan izinkan akses kamera lalu muat ulang halaman.');
            }
        }

        // Cek berkala apakah ada HP/benda mencurigakan lain di frame kamera
        function startPhoneDetectionLoop() {
            const FLAGGED_CLASSES = ['cell phone', 'remote', 'laptop']; // kelas COCO-SSD yang dianggap mencurigakan
            setInterval(async () => {
                if (!examStarted || isFinishing || !detectionModel || webcamVideo.readyState < 2) return;
                try {
                    const predictions = await detectionModel.detect(webcamVideo);
                    const found = predictions.find(p => FLAGGED_CLASSES.includes(p.class) && p.score >= 0.6);

                    if (found) {
                        phoneStrikeCount++;
                        // Wajib terdeteksi 2x berturut-turut dulu, biar tidak salah-lapor karena 1 frame buram/keliru
                        if (phoneStrikeCount >= 2) {
                            phoneStrikeCount = 0;
                            reportViolation(`perangkat lain terdeteksi (${found.class})`);
                        }
                    } else {
                        phoneStrikeCount = 0;
                    }
                } catch (e) {
                    console.error('Deteksi objek gagal', e);
                }
            }, 1500); // cek setiap 1.5 detik
        }

        // 1. Mulai ujian: wajib klik dulu (browser mewajibkan gesture user sebelum fullscreen diizinkan)
        document.getElementById('start-btn').addEventListener('click', async () => {
            try {
                await document.documentElement.requestFullscreen();
            } catch (e) {
                console.warn('Fullscreen ditolak/tidak didukung, ujian tetap lanjut tanpa fullscreen.', e);
            }
            overlay.classList.add('hidden');
            examStarted = true;
            await startWebcam();
        });

        // 2. Deteksi pindah tab / minimize / app switch
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                reportViolation('pindah tab / minimize');
            }
        });

        // 3. Deteksi keluar dari mode fullscreen
        document.addEventListener('fullscreenchange', () => {
            if (examStarted && !isFinishing && !document.fullscreenElement) {
                reportViolation('keluar fullscreen');
            }
        });

        // 4. Cegah klik kanan & beberapa shortcut devtools (best effort, bukan jaminan mutlak)
        document.addEventListener('contextmenu', (e) => { if (examStarted) e.preventDefault(); });
        document.addEventListener('keydown', (e) => {
            if (!examStarted) return;
            const blocked = (e.key === 'F12') ||
                             (e.ctrlKey && e.shiftKey && ['I', 'J', 'C'].includes(e.key)) ||
                             (e.ctrlKey && e.key === 'u');
            if (blocked) e.preventDefault();
        });

        // 5. Tutup tab / navigasi keluar / kembali (back) -> peringatan + gugurkan sesi via beacon
        window.addEventListener('beforeunload', (e) => {
            if (!examStarted || isFinishing) return;
            const payload = new URLSearchParams({ room_id: ROOM_ID, csrf_token: CSRF_TOKEN }).toString();
            navigator.sendBeacon(LEAVE_URL, new Blob([payload], { type: 'application/x-www-form-urlencoded' }));
            e.preventDefault();
            e.returnValue = 'Meninggalkan halaman ini akan menggugurkan ujian Anda. Yakin?';
            return e.returnValue;
        });

        function stopWebcam() {
            if (webcamStream) {
                webcamStream.getTracks().forEach(track => track.stop());
            }
            webcamBox.classList.add('hidden');
        }

        // 6. Submit resmi form ujian tidak boleh ikut ke-treat sebagai pelanggaran
        document.getElementById('code-form').addEventListener('submit', () => {
            isFinishing = true;
            stopWebcam();
        });
    </script>
</body>
</html>