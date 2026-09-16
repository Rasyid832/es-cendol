<?php
session_start();

$host = "localhost";
$user = "root";
$pass = "";
$dbname = "codeprocess_db";

$conn = @new mysqli($host, $user, $pass, $dbname);

$user_id = $_SESSION['user_id'] ?? (isset($_GET['student_id']) ? intval($_GET['student_id']) : 1);
$user_name = $_SESSION['name'] ?? 'SISWA';
$identity_number = $_SESSION['identity_number'] ?? 'SISWA';

$room_id = isset($_GET['room_id']) ? intval($_GET['room_id']) : 1;
$exam_title = "Pengerjaan Ujian Coding";
$duration_minutes = 60;

if ($conn && !$conn->connect_error) {
    $query_room = "SELECT * FROM exam_rooms WHERE id = ?";
    if ($stmt = $conn->prepare($query_room)) {
        $stmt->bind_param("i", $room_id);
        $stmt->execute();
        $room_result = $stmt->get_result();
        if ($room_result->num_rows > 0) {
            $room = $room_result->fetch_assoc();
            $exam_title = $room['title'];
            $duration_minutes = $room['duration_minutes'];
        }
    }
}

$message = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_code'])) {
    $submitted_code = $_POST['code_input'] ?? '';
    $language = $_POST['language'] ?? 'javascript';

    if ($conn && !$conn->connect_error && $user_id) {
        $json_data = json_encode(['language' => $language, 'code' => $submitted_code]);
        $insert_query = "INSERT INTO telemetry_logs (student_id, room_id, flight_time_data) VALUES (?, ?, ?)";
        $stmt_insert = $conn->prepare($insert_query);
        $stmt_insert->bind_param("iis", $user_id, $room_id, $json_data);

        if ($stmt_insert->execute()) {
            $message = "<script>alert('Kode berhasil disimpan ke database!');</script>";
        } else {
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
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/peerjs@1.5.2/dist/peerjs.min.js"></script>
    <style>
        body { font-family: 'Poppins', sans-serif; }
    </style>
</head>
<body class="bg-[#B08D8D] h-screen flex flex-col p-6 overflow-hidden relative">

    <div id="permission-modal" class="fixed inset-0 bg-black/90 backdrop-blur-md z-50 flex flex-col items-center justify-center p-6 text-center text-white">
        <div class="bg-gray-800 border border-gray-700 p-8 rounded-2xl max-w-md w-full shadow-2xl flex flex-col items-center">
            <div class="w-16 h-16 bg-red-500/20 text-red-500 rounded-full flex items-center justify-center mb-4">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                </svg>
            </div>
            <h2 class="text-xl font-bold mb-2">Akses Seluruh Layar & Kamera Diperlukan</h2>
            <p class="text-xs text-gray-300 mb-4">
                Untuk memulai ujian, Anda WAJIB memilih <span class="text-amber-400 font-semibold">"Entire Screen / Seluruh Layar"</span> saat pop-up browser muncul.
            </p>
            <div class="bg-amber-500/10 border border-amber-500/30 rounded-lg p-3 text-left text-xs text-amber-300 mb-6">
                ⚠️ <strong>PENTING:</strong> Memilih <em>Window</em> atau <em>Tab Browser</em> akan ditolak secara otomatis oleh sistem.
            </div>
            
            <button onclick="requestStreams()" class="w-full bg-indigo-600 hover:bg-indigo-700 active:scale-95 text-white font-semibold py-3 rounded-xl transition-all shadow-lg text-sm">
                Mulai Share Entire Screen & Kamera
            </button>
        </div>
    </div>

    <?php echo $message; ?>

    <div class="bg-[#E0E0E0] rounded-2xl shadow-2xl flex-1 flex flex-col overflow-hidden">

        <div class="flex justify-between items-center px-8 py-4 border-b border-gray-300">
            <div class="font-bold text-lg tracking-wider text-black flex items-center gap-4">
                <span>NAMA: <span class="font-normal text-gray-800"><?= htmlspecialchars($user_name) ?> (<?= htmlspecialchars($identity_number) ?>)</span></span>
                
                <div class="flex items-center gap-2 bg-white px-3 py-1 rounded-lg border text-xs">
                    <span id="screenshare-status" class="text-green-600 font-semibold">🟢 Pengawasan Aktif</span>
                </div>
            </div>

            <div class="font-bold text-lg tracking-wider text-black">
                TIMER <span id="timer-display" class="text-red-600 font-mono ml-2">00:00:00</span>
            </div>
        </div>

        <form id="code-form" method="POST" class="flex-1 grid grid-cols-3 gap-6 p-6 overflow-hidden">
            
            <div class="bg-white rounded-xl p-6 flex flex-col justify-start overflow-y-auto shadow-sm border border-gray-200">
                <h2 class="text-xl font-bold mb-4 text-gray-800 border-b pb-2"><?= htmlspecialchars($exam_title) ?></h2>
                <div class="text-gray-600 space-y-3 text-sm">
                    <p><strong>Deskripsi Soal:</strong></p>
                    <p>Pilihlah bahasa pemrograman yang ingin digunakan di atas editor kode. Buatlah program yang mencetak output sesuai spesifikasi.</p>
                </div>
            </div>

            <div class="bg-white rounded-xl flex flex-col overflow-hidden shadow-sm border border-gray-200">
                <div class="bg-gray-100 px-4 py-2 border-b border-gray-200 flex justify-between items-center">
                    <span class="text-xs font-semibold text-gray-500 tracking-wider">CODE EDITOR</span>
                    
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

            <div class="bg-white rounded-xl p-5 flex flex-col relative justify-between shadow-sm border border-gray-200">
                <div class="flex justify-end mb-4">
                    <button type="submit" name="submit_code" class="bg-red-600 hover:bg-red-700 active:scale-95 text-white font-semibold px-8 py-2.5 rounded-xl shadow-md transition-all text-sm tracking-wide">
                        Submit
                    </button>
                </div>

                <div class="bg-black text-green-400 font-mono text-xs p-4 rounded-xl flex-1 overflow-hidden flex flex-col justify-between border border-gray-800">
                    <div id="terminal-output" class="flex-1 overflow-y-auto space-y-1 mb-2 pr-1">
                        <p class="text-gray-500">// Terminal Output</p>
                        <p class="text-gray-400">> System Ready...</p>
                    </div>

                    <div class="mb-2">
                        <input type="text" id="stdin-input" placeholder="Input variabel/stdin (opsional)..." class="w-full bg-gray-900 text-gray-300 border border-gray-700 rounded px-2 py-1 text-xs focus:outline-none focus:border-green-500">
                    </div>

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

    <script>
        const studentId = "<?= $user_id ?>";
        const roomId = "<?= $room_id ?>";
        
        const peerID_Screen = `codeprocess-screen-${studentId}-${roomId}`;
        const peerID_Cam = `codeprocess-cam-${studentId}-${roomId}`;

        const peerScreen = new Peer(peerID_Screen);
        const peerCam = new Peer(peerID_Cam);

        let screenStream = null;
        let camStream = null;

        async function requestStreams() {
            try {
                screenStream = await navigator.mediaDevices.getDisplayMedia({ 
                    video: { 
                        displaySurface: "monitor", 
                        cursor: "always" 
                    }, 
                    audio: false 
                });

                const videoTrack = screenStream.getVideoTracks()[0];
                const settings = videoTrack.getSettings();

                if (settings.displaySurface && settings.displaySurface !== 'monitor') {
                    videoTrack.stop();
                    alert("⚠️ Ditolak! Anda WAJIB membagikan seluruh layar / entire screen, bukan Window atau Tab!");
                    return;
                }

                camStream = await navigator.mediaDevices.getUserMedia({ video: true, audio: false });

                document.getElementById('permission-modal').style.display = 'none';

                peerScreen.on('call', call => call.answer(screenStream));
                peerCam.on('call', call => call.answer(camStream));

                screenStream.getVideoTracks()[0].onended = lockExam;
                camStream.getVideoTracks()[0].onended = lockExam;

            } catch (err) {
                console.error(err);
                alert("Gagal mengaktifkan pengawasan! Pastikan Anda menyetujui izin Layar dan Kamera.");
            }
        }

        function lockExam() {
            alert("Akses Seluruh Layar atau Kamera terputus! Ujian telah dikunci otomatis.");
            document.getElementById('permission-modal').style.display = 'flex';
        }

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
                alert("Waktu habis! Kode Anda disubmit otomatis.");
                document.getElementById('code-form').submit();
            }
        }
        setInterval(updateTimer, 1000);

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
        
        changeLanguageTemplate();

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
    </script>
</body>
</html>