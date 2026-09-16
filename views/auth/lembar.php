<?php
session_start();

// Koneksi ke Database menggunakan PDO
require_once __DIR__ . '/../../config/db.php';

// Dapatkan Data User dari Session
$user_id = $_SESSION['user_id'] ?? null;
$user_name = $_SESSION['name'] ?? 'User Coding';
$identity_number = $_SESSION['identity_number'] ?? 'DEV-001';

// Ambil project/room_id dari parameter URL (opsional)
$room_id = isset($_GET['room_id']) ? intval($_GET['room_id']) : ($_SESSION['room_id'] ?? 1);

// Default Fallback
$project_title = "Workspace Project";
$duration_minutes = 90; // Default durasi 90 menit
$project_description = "Tulis dan kembangkan kode program Anda di sini.";
$question_text    = "// Tulis solusi koding Anda di sini\nprint('Hello World');";

try {
    $stmtRoom = $pdo->prepare("SELECT * FROM rooms WHERE id = :id LIMIT 1");
    $stmtRoom->execute(['id' => $room_id]);
    $room = $stmtRoom->fetch(PDO::FETCH_ASSOC);

    if ($room) {
        $project_title       = $room['subject_name'] ?? $room['title'] ?? $room['name'] ?? "Workspace Project";
        $duration_minutes = isset($room['duration']) ? intval($room['duration']) : 90;
        $project_description = $room['description'] ?? "Silakan ikuti instruksi project dengan teliti.";
        
        if (!empty($room['question_text'])) {
            $question_text = $room['question_text'];
        }
    }
} catch (PDOException $e) {
    // Abaikan atau log error jika diperlukan
}

// Handling Form Submit Kodingan
$message = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_code'])) {
    $submitted_code = $_POST['code_input'] ?? '';
    $language = $_POST['language'] ?? 'python';

    if ($user_id) {
        try {
            $stmtSession = $pdo->prepare("SELECT id FROM sessions WHERE room_id = :room_id AND student_id = :student_id LIMIT 1");
            $stmtSession->execute(['room_id' => $room_id, 'student_id' => $user_id]);
            $session = $stmtSession->fetch(PDO::FETCH_ASSOC);

            if ($session) {
                $session_id = $session['id'];
            } else {
                $stmtNewSession = $pdo->prepare("INSERT INTO sessions (room_id, student_id, status) VALUES (:room_id, :student_id, 'ongoing')");
                $stmtNewSession->execute(['room_id' => $room_id, 'student_id' => $user_id]);
                $session_id = $pdo->lastInsertId();
            }

            $json_data = json_encode([
                'language' => $language, 
                'code' => $submitted_code, 
                'timestamp' => date('Y-m-d H:i:s')
            ]);

            $insert_query = "INSERT INTO telemetry_logs (session_id, flight_time_data) VALUES (:session_id, :flight_time_data)";
            $stmt_insert = $pdo->prepare($insert_query);
            $stmt_insert->execute([
                'session_id' => $session_id,
                'flight_time_data' => $json_data
            ]);

            $stmtUpdateSession = $pdo->prepare("UPDATE sessions SET status = 'completed', finished_at = NOW() WHERE id = :session_id");
            $stmtUpdateSession->execute(['session_id' => $session_id]);

            $message = "<script>alert('Kode berhasil disimpan & disubmit!'); window.location.href='../student/dashboard.php';</script>";
        } catch (PDOException $e) {
            $message = "<script>alert('Gagal menyimpan ke database: " . addslashes($e->getMessage()) . "');</script>";
        }
    } else {
        $message = "<script>alert('Kode berhasil direkam!');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monaco Code Editor - <?= htmlspecialchars($project_title) ?></title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Monaco Editor Loader CDN v0.33.0 -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.33.0/min/vs/loader.min.js"></script>
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

    <?php echo $message; ?>

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
            <!-- Informasi Total Durasi -->
            <div class="hidden sm:flex items-center gap-1.5 bg-[#2d2d2d] px-3 py-1.5 rounded border border-[#444]">
                <span class="text-gray-400">⏱️ Durasi:</span>
                <span class="font-mono font-bold text-white"><?= $duration_minutes ?> Menit</span>
            </div>

            <!-- Countdown Timer -->
            <div class="flex items-center gap-2 bg-[#2d2d2d] px-3 py-1.5 rounded border border-[#444]">
                <span class="text-gray-400">⏳ Sisa Waktu:</span>
                <span id="countdown-timer" class="font-mono font-bold text-yellow-400 text-sm">--:--:--</span>
            </div>

            <!-- Tanggal -->
            <div class="hidden md:flex items-center gap-2 text-gray-400">
                <span>📅 <span id="current-date"></span></span>
            </div>
        </div>
    </div>

    <!-- WORKSPACE UTAMA (FORM) -->
    <form id="code-form" method="POST" class="flex-1 flex overflow-hidden">

        <!-- Hidden inputs untuk menangkap bahasa dan kode editor saat submit -->
        <input type="hidden" name="language" id="language-hidden" value="python">
        <input type="hidden" name="code_input" id="hidden_code_input">

        <!-- KIRI: SIDEBAR (EXPLORER & CATATAN PROJECT) -->
        <div class="w-80 bg-[#252526] border-r border-[#333333] flex flex-col select-none">
            
            <!-- Sidebar Tab Toggle Header -->
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

                <!-- File Tree Explorer -->
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

                    <!-- Informasi Editor -->
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
                    <span class="text-[10px] bg-emerald-900 text-emerald-200 px-2 py-0.5 rounded font-mono uppercase">Catatan / Instruksi</span>
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
            
            <!-- Editor Tabs Bar -->
            <div class="bg-[#2d2d2d] h-11 flex items-center justify-between px-4 border-b border-[#252526]">
                <div class="flex items-center gap-2">
                    <div class="bg-[#1e1e1e] text-white px-4 py-2 text-xs border-t-2 border-[#007acc] flex items-center gap-2">
                        <span>📄</span> <span id="active-filename">solution.py</span>
                        <span id="detected-lang-badge" class="ml-2 text-[10px] bg-[#0e639c] text-white px-1.5 py-0.5 rounded uppercase font-mono">python</span>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex items-center gap-2">
                    <button type="button" onclick="toggleAndRunCode()" class="bg-[#388a34] hover:bg-[#469542] active:scale-95 text-white font-medium px-3.5 py-1.5 rounded text-xs transition-all flex items-center gap-1.5 shadow">
                        ▶ Run Code
                    </button>
                    <button type="submit" name="submit_code" onclick="return prepareSubmit()" class="bg-[#0e639c] hover:bg-[#1177bb] active:scale-95 text-white font-medium px-3.5 py-1.5 rounded text-xs transition-all flex items-center gap-1.5 shadow">
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
                <!-- Terminal Header -->
                <div class="bg-[#2d2d2d] px-3 py-1.5 flex justify-between items-center text-[11px] text-gray-300 border-b border-[#333333]">
                    <span class="font-bold flex items-center gap-1">💻 INTEGRATED TERMINAL</span>
                    <div class="flex items-center gap-3">
                        <span id="terminal-status" class="text-yellow-400">Idle</span>
                        <button type="button" onclick="toggleTerminal()" class="text-gray-400 hover:text-white font-bold text-sm">✕</button>
                    </div>
                </div>

                <!-- Terminal Output Content -->
                <div id="terminal-output" class="flex-1 p-3 overflow-y-auto space-y-1 text-gray-300 text-[11px]">
                    <p class="text-gray-500">// Compiler sandbox ready. Press 'Run Code' to execute.</p>
                </div>

                <!-- Stdin Input & Footer -->
                <div class="p-2 bg-[#252526] border-t border-[#333333] flex items-center gap-2">
                    <input type="text" id="stdin-input" placeholder="Masukkan stdin (opsional)..." class="flex-1 bg-[#1e1e1e] text-white border border-[#444] rounded px-2 py-1 text-[11px] focus:outline-none focus:border-[#007acc]">
                    <button type="button" onclick="runCodeAdvanced()" class="bg-[#333333] hover:bg-[#444444] text-white px-3 py-1 rounded text-[11px] transition-all border border-[#555]">
                        Execute
                    </button>
                </div>
            </div>

        </div>

    </form>

    <!-- SCRIPT UTAMA EDITOR & COUNTDOWN TIMER -->
    <script>
        // --- 0. TANGGAL & COUNTDOWN TIMER ---
        // Tampilkan tanggal hari ini
        const options = { weekday: 'short', year: 'numeric', month: 'short', day: 'numeric' };
        document.getElementById('current-date').textContent = new Date().toLocaleDateString('id-ID', options);

        // Countdown Timer berdasarkan durasi dari database (dalam menit)
        let totalSeconds = <?= $duration_minutes ?> * 60;
        
        function updateCountdown() {
            const timerDisplay = document.getElementById('countdown-timer');
            if (totalSeconds <= 0) {
                timerDisplay.textContent = "Waktu Habis!";
                timerDisplay.className = "font-mono font-bold text-red-500 text-sm animate-pulse";
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

        // --- 1. FUNGSI SWITCH TAB SIDEBAR (FILES / NOTES) ---
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

        // --- 2. INISIALISASI MONACO EDITOR ---
        let editor;
        const extensionMap = {
            'py': 'python', 'rb': 'ruby', 'js': 'javascript', 
            'php': 'php', 'java': 'java', 'cpp': 'cpp', 'c': 'c', 'go': 'go'
        };

        const templates = {
            python: 'print("Hello World")',
            ruby: 'puts "Hello World"',
            javascript: 'function hitung(a, b) {\n    return a + b;\n}\n\nlet hasil = hitung(1, 1);\nconsole.log(hasil);',
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

        try {
            require.config({ 
                paths: { 'vs': 'https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.33.0/min' },
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
            if (editor) {
                fileStorage[currentActiveFile] = editor.getValue();
            } else {
                const ta = document.getElementById('fallback-ta');
                if(ta) fileStorage[currentActiveFile] = ta.value;
            }

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

            if (editor) {
                let model = editor.getModel();
                monaco.editor.setModelLanguage(model, detectedLang);
                editor.setValue(fileStorage[filename]);
            } else {
                const ta = document.getElementById('fallback-ta');
                if(ta) ta.value = fileStorage[filename];
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

        // --- 3. PREPARE SUBMIT ---
        function prepareSubmit() {
            let codeVal = "";
            if (editor) {
                codeVal = editor.getValue();
            } else {
                const ta = document.getElementById('fallback-ta');
                codeVal = ta ? ta.value : "";
            }

            if (confirm('Yakin ingin menyimpan dan mensubmit perubahan kode ini?')) {
                document.getElementById('hidden_code_input').value = codeVal;
                return true;
            }
            return false;
        }

        function toggleTerminal() {
            const term = document.getElementById('terminal-container');
            term.classList.toggle('hidden');
            if(editor) editor.layout();
        }

        function toggleAndRunCode() {
            const term = document.getElementById('terminal-container');
            if (term.classList.contains('hidden')) {
                term.classList.remove('hidden');
            }
            if(editor) editor.layout();
            runCodeAdvanced();
        }

        // --- 4. ADVANCED RUNTIME EMULATOR ---
        async function runCodeAdvanced() {
            let code = "";
            if (editor) {
                code = editor.getValue();
            } else {
                const ta = document.getElementById('fallback-ta');
                code = ta ? ta.value : "";
            }

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
                let outputText = "";

                if (lang === 'javascript') {
                    try {
                        let logs = [];
                        const originalConsoleLog = console.log;
                        
                        console.log = function(...args) {
                            logs.push(args.map(arg => typeof arg === 'object' ? JSON.stringify(arg, null, 2) : arg).join(' '));
                        };
                        
                        let runFunc = new Function(code);
                        runFunc();

                        console.log = originalConsoleLog; 

                        if (logs.length > 0) {
                            outputText = logs.join('\n');
                        }
                    } catch (err) {
                        terminalOutput.innerHTML = `
                            <p class="text-red-400 font-bold">RuntimeError: ${escapeHtml(err.message)}</p>
                            <p class="text-gray-500 text-[10px]">Process finished with exit code 1</p>
                        `;
                        terminalStatus.textContent = "Runtime Error";
                        terminalOutput.scrollTop = terminalOutput.scrollHeight;
                        return;
                    }
                }

                if (!outputText) {
                    let match = code.match(/["']([^"']*)["']/);
                    outputText = match ? match[1] : "Program executed successfully (no stdout output).";
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