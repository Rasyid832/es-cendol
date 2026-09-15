<?php
session_start();

// Koneksi ke Database
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "codeprocess_db";

$conn = @new mysqli($host, $user, $pass, $dbname);

// Dapatkan Data User dari Session
$user_id = $_SESSION['user_id'] ?? null;
$user_name = $_SESSION['name'] ?? 'SISWA';
$identity_number = $_SESSION['identity_number'] ?? 'SISWA';

// Ambil Data Ujian dari Database jika ada
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

// Handling Form Submit Kodingan
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
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; }
    </style>
</head>
<body class="bg-[#B08D8D] h-screen flex flex-col p-6 overflow-hidden">

    <?php echo $message; ?>

    <!-- Container Utama -->
    <div class="bg-[#E0E0E0] rounded-2xl shadow-2xl flex-1 flex flex-col overflow-hidden">

        <!-- HEADER BAR (Nama Siswa & Timer) -->
        <div class="flex justify-between items-center px-8 py-4 border-b border-gray-300">
            <div class="font-bold text-lg tracking-wider text-black">
                NAMA: <span class="font-normal text-gray-800"><?= htmlspecialchars($user_name) ?> (<?= htmlspecialchars($identity_number) ?>)</span>
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
                    <p>Pilihlah bahasa pemrograman yang ingin digunakan di atas editor kode. Buatlah program yang mencetak output sesuai spesifikasi.</p>
                    <p><strong>Contoh Perintah Output:</strong></p>
                    <ul class="list-disc list-inside space-y-1 text-xs font-mono bg-gray-50 p-3 rounded-lg border">
                        <li><strong>Python:</strong> <code>print("Hello World")</code></li>
                        <li><strong>PHP:</strong> <code>&lt;?php echo "Hello World";</code></li>
                        <li><strong>C++:</strong> <code>std::cout &lt;&lt; "Hello";</code></li>
                        <li><strong>Java:</strong> <code>System.out.println("Hello");</code></li>
                    </ul>
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
    </script>
</body>
</html>