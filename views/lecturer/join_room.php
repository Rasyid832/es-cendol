<?php
session_start();

// Proteksi halaman: Wajib login & ber-role 'lecturer'
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'lecturer') {
    header("Location: ../auth/login.php");
    exit();
}

// Tangkap pesan error dari session jika ada
$error_message = $_SESSION['error'] ?? '';
unset($_SESSION['error']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk Room Ujian - CodeProcess</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Fira+Code:wght@400;600&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        body { font-family: 'Poppins', sans-serif; transition: background-color 0.5s ease, color 0.5s ease; }
        .font-mono-code { font-family: 'Fira Code', monospace; }

        /* ROTATE ANIMATION UNTUK BORDER NEON */
        @keyframes rotateBorder {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* POWER-ON / DARK MODE GLOBAL */
        body.power-on {
            background-color: #0b0f19 !important;
            color: #f8fafc;
        }

        /* NEON BORDER WRAPPER CARD */
        .neon-border-wrapper {
            position: relative;
            border-radius: 1.5rem;
            padding: 2px;
            overflow: hidden;
            transition: all 0.5s ease;
        }

        .power-on .neon-border-wrapper::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: conic-gradient(
                transparent 0deg,
                transparent 280deg,
                #10b981 310deg,
                #6366f1 360deg
            );
            animation: rotateBorder 4s linear infinite;
            z-index: 0;
        }

        /* CARD INNER CONTAINER */
        .cyber-card {
            position: relative;
            z-index: 1;
            border-radius: 1.4rem;
            transition: background-color 0.5s ease, border-color 0.5s ease, box-shadow 0.5s ease;
        }

        .power-on .cyber-card {
            background-color: #0f172a !important;
            border-color: transparent !important;
            box-shadow: 0 0 25px rgba(16, 185, 129, 0.15), 0 0 10px rgba(99, 102, 241, 0.2);
        }

        /* INPUT FIELD CYBER STYLE */
        .cyber-input {
            transition: all 0.3s ease;
        }

        .power-on .cyber-input {
            background-color: #0b0f19 !important;
            border-color: #334155 !important;
            color: #f8fafc !important;
        }

        .power-on .cyber-input:focus {
            border-color: #10b981 !important;
            box-shadow: 0 0 10px rgba(16, 185, 129, 0.3);
        }

        .power-on .cyber-input::placeholder {
            color: #64748b !important;
        }

        /* TEXT COLOR MODIFIERS */
        .power-on .text-main-title { color: #ffffff !important; }
        .power-on .text-sub-title { color: #94a3b8 !important; }

        /* ROBOT HOVERING & SHADOW ANIMATION */
        @keyframes floatDrone {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-8px) rotate(-2deg); }
        }

        @keyframes shadowScale {
            0%, 100% { transform: scale(1); opacity: 0.3; }
            50% { transform: scale(0.7); opacity: 0.15; }
        }

        .drone-floating { animation: floatDrone 3.5s ease-in-out infinite; }
        .drone-shadow { animation: shadowScale 3.5s ease-in-out infinite; }
    </style>
</head>
<body class="bg-slate-100 text-slate-800 min-h-screen flex" id="main-body">

    <!-- Memanggil Sidebar Navigasi Kiri -->
    <?php 
    if (file_exists('sidebar.php')) {
        include 'sidebar.php'; 
    }
    ?>

    <!-- Area Main Content -->
    <main class="flex-1 p-8 flex flex-col justify-center items-center relative">

        <!-- MASKOT ROBOT CYBER DRONE (POSISI ATAS CARD) -->
        <div onclick="togglePower()" class="drone-floating cursor-pointer group flex flex-col items-center transition-all duration-500 ease-in-out mb-6">
            <div id="robot-speech" class="bg-slate-900/90 backdrop-blur-md text-white text-[11px] font-semibold px-3.5 py-1.5 rounded-full shadow-xl mb-2 border border-emerald-500/40 group-hover:scale-105 transition-all duration-500 flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span>
                <span id="speech-text">Klik untuk pindah ke Dark Mode</span>
            </div>

            <div id="robot-body" class="relative w-14 h-14 bg-gradient-to-b from-white to-slate-100 rounded-3xl shadow-xl border-2 border-emerald-200/80 flex flex-col items-center justify-center transition-all duration-500 ease-in-out group-hover:border-emerald-500 group-hover:shadow-emerald-400/40">
                <div class="absolute -top-2 flex justify-between w-7">
                    <div id="ant-1" class="w-1 h-2 bg-slate-300 rounded-full transition-all duration-500"></div>
                    <div id="ant-2" class="w-1 h-2 bg-slate-300 rounded-full transition-all duration-500"></div>
                </div>

                <div id="robot-visor" class="w-10 h-6 bg-slate-900 rounded-2xl border border-slate-700/60 flex items-center justify-center gap-1.5 shadow-inner transition-all duration-500">
                    <div id="eye-left" class="w-2 h-2 rounded-full bg-emerald-400 shadow-[0_0_8px_#34d399] transition-all duration-500"></div>
                    <div id="eye-right" class="w-2 h-2 rounded-full bg-emerald-400 shadow-[0_0_8px_#34d399] transition-all duration-500"></div>
                </div>

                <div id="pod-left" class="absolute -left-1.5 w-1.5 h-3.5 bg-emerald-200 rounded-l-md transition-all duration-500"></div>
                <div id="pod-right" class="absolute -right-1.5 w-1.5 h-3.5 bg-emerald-200 rounded-r-md transition-all duration-500"></div>
            </div>

            <div class="drone-shadow w-9 h-1.5 bg-slate-900 rounded-full mt-1.5 blur-[2px]"></div>
        </div>

        <!-- WRAPPER CARD NEON FORM -->
        <div class="max-w-md w-full neon-border-wrapper">
            <div class="cyber-card bg-white border border-slate-200 rounded-2xl p-6 space-y-6 shadow-xl">
                
                <!-- Card Header -->
                <div class="text-center space-y-2">
                    <div class="w-12 h-12 bg-emerald-500/10 border border-emerald-500/30 text-emerald-500 rounded-2xl flex items-center justify-center mx-auto transition-colors" id="icon-container">
                        <i class="fa-solid fa-right-to-bracket text-xl"></i>
                    </div>
                    <h2 class="text-xl font-bold text-slate-900 tracking-tight text-main-title transition-colors">Masuk Ruang Monitoring</h2>
                    <p class="text-xs text-slate-500 text-sub-title transition-colors">Masukkan Room ID dan NIP Dosen untuk mulai pengawasan.</p>
                </div>

                <!-- Pesan Alert Error Jika Validasi Gagal -->
                <?php if (!empty($error_message)): ?>
                    <div class="bg-rose-500/10 border border-rose-500/30 text-rose-400 p-3.5 rounded-xl text-xs flex items-center gap-2.5">
                        <i class="fa-solid fa-circle-exclamation text-sm shrink-0"></i>
                        <span><?= htmlspecialchars($error_message) ?></span>
                    </div>
                <?php endif; ?>

                <!-- Form Join Room -->
                <form action="../../controllers/controller_join_room.php" method="POST" class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1.5 text-sub-title">Room ID Ujian</label>
                        <input type="text" name="room_id" placeholder="Contoh: CS101A" required class="cyber-input font-mono-code w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-3 text-sm text-slate-900 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 outline-none uppercase placeholder:text-slate-400">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1.5 text-sub-title">Nomor Induk Dosen (NIP)</label>
                        <input type="text" name="nip" placeholder="Masukkan NIP Anda" required class="cyber-input w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-3 text-sm text-slate-900 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 outline-none placeholder:text-slate-400">
                    </div>

                    <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-500 text-white font-semibold py-3 px-4 rounded-xl text-sm transition-all duration-300 flex items-center justify-center gap-2 shadow-lg shadow-emerald-600/20 hover:shadow-emerald-500/40">
                        <i class="fa-solid fa-shield-check"></i>
                        Validasi & Masuk Room
                    </button>
                </form>

            </div>
        </div>
    </main>

    <!-- SCRIPT MODE TOGGLE LOGIC -->
    <script>
        const mainBody = document.getElementById('main-body');
        const iconContainer = document.getElementById('icon-container');

        // Elemen Drone Maskot
        const robotBody = document.getElementById('robot-body');
        const robotVisor = document.getElementById('robot-visor');
        const eyeLeft = document.getElementById('eye-left');
        const eyeRight = document.getElementById('eye-right');
        const ant1 = document.getElementById('ant-1');
        const ant2 = document.getElementById('ant-2');
        const podLeft = document.getElementById('pod-left');
        const podRight = document.getElementById('pod-right');
        const speechText = document.getElementById('speech-text');

        let isPowerOn = false;

        function togglePower() {
            isPowerOn = !isPowerOn;

            if (isPowerOn) {
                // Background & Dark Mode Active
                mainBody.classList.add('power-on');
                iconContainer.className = "w-12 h-12 bg-emerald-500/20 border border-emerald-400/40 text-emerald-400 rounded-2xl flex items-center justify-center mx-auto transition-colors shadow-[0_0_15px_rgba(16,185,129,0.2)]";

                // Drone Robot Glow Mode
                robotBody.className = "relative w-14 h-14 bg-slate-900 rounded-3xl shadow-[0_0_25px_rgba(16,185,129,0.4)] border-2 border-emerald-400 flex flex-col items-center justify-center transition-all duration-500 ease-in-out";
                robotVisor.className = "w-10 h-6 bg-black rounded-2xl border border-emerald-500/50 flex items-center justify-center gap-1.5 shadow-[inset_0_0_10px_rgba(16,185,129,0.3)] transition-all duration-500";
                
                eyeLeft.className = "w-2 h-2 rounded-full bg-emerald-400 shadow-[0_0_10px_#34d399] animate-pulse transition-all duration-500";
                eyeRight.className = "w-2 h-2 rounded-full bg-emerald-400 shadow-[0_0_10px_#34d399] animate-pulse transition-all duration-500";
                
                ant1.className = "w-1 h-2 bg-emerald-400 shadow-[0_0_6px_#34d399] rounded-full transition-all duration-500";
                ant2.className = "w-1 h-2 bg-emerald-400 shadow-[0_0_6px_#34d399] rounded-full transition-all duration-500";

                podLeft.className = "absolute -left-1.5 w-1.5 h-3.5 bg-emerald-500 rounded-l-md transition-all duration-500 shadow-[0_0_6px_#34d399]";
                podRight.className = "absolute -right-1.5 w-1.5 h-3.5 bg-emerald-500 rounded-r-md transition-all duration-500 shadow-[0_0_6px_#34d399]";

                speechText.textContent = "Klik untuk pindah ke Clear Mode";
                localStorage.setItem('theme_mode', 'dark');
            } else {
                // Reset Clear Mode
                mainBody.classList.remove('power-on');
                iconContainer.className = "w-12 h-12 bg-emerald-500/10 border border-emerald-500/30 text-emerald-500 rounded-2xl flex items-center justify-center mx-auto transition-colors";

                // Reset Drone Robot Appearance
                robotBody.className = "relative w-14 h-14 bg-gradient-to-b from-white to-slate-100 rounded-3xl shadow-xl border-2 border-emerald-200/80 flex flex-col items-center justify-center transition-all duration-500 ease-in-out group-hover:border-emerald-500 group-hover:shadow-emerald-400/40";
                robotVisor.className = "w-10 h-6 bg-slate-900 rounded-2xl border border-slate-700/60 flex items-center justify-center gap-1.5 shadow-inner transition-all duration-500";
                
                eyeLeft.className = "w-2 h-2 rounded-full bg-emerald-400 shadow-[0_0_8px_#34d399] transition-all duration-500";
                eyeRight.className = "w-2 h-2 rounded-full bg-emerald-400 shadow-[0_0_8px_#34d399] transition-all duration-500";

                ant1.className = "w-1 h-2 bg-slate-300 rounded-full transition-all duration-500";
                ant2.className = "w-1 h-2 bg-slate-300 rounded-full transition-all duration-500";

                podLeft.className = "absolute -left-1.5 w-1.5 h-3.5 bg-emerald-200 rounded-l-md transition-all duration-500";
                podRight.className = "absolute -right-1.5 w-1.5 h-3.5 bg-emerald-200 rounded-r-md transition-all duration-500";

                speechText.textContent = "Klik untuk pindah ke Dark Mode";
                localStorage.setItem('theme_mode', 'clear');
            }
        }

        // Persistent Mode Sync via LocalStorage
        if (localStorage.getItem('theme_mode') === 'dark') {
            togglePower();
        }
    </script>
</body>
</html>