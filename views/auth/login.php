<?php
session_start();
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'student') header("Location: ../student/dashboard.php");
    elseif ($_SESSION['role'] === 'lecturer') header("Location: ../GURU/DashboardGuru.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CodeProcess - Login & Sign Up</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Fira+Code:wght@400;600&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        body { font-family: 'Poppins', sans-serif; transition: background-color 0.5s ease; }
        .font-mono-code { font-family: 'Fira Code', monospace; }

        .overlay-bg {
            background-image: linear-gradient(rgba(79, 70, 229, 0.75), rgba(30, 27, 75, 0.85)), url('../../assets/login-bg.jpg');
            background-size: cover;
            background-position: center;
        }

        /* Dual Panel Animations */
        .container-box.right-panel-active .sign-in-container {
            transform: translateX(100%);
            opacity: 0;
            z-index: 1;
        }

        .container-box.right-panel-active .sign-up-container {
            transform: translateX(0%);
            opacity: 1;
            z-index: 5;
            animation: show 0.6s;
        }

        @keyframes show {
            0%, 49.99% { opacity: 0; z-index: 1; }
            50%, 100% { opacity: 1; z-index: 5; }
        }

        .container-box.right-panel-active .overlay-container { transform: translateX(-100%); }
        .container-box.right-panel-active .overlay { transform: translateX(50%); }
        .container-box.right-panel-active .overlay-left { transform: translateX(0); }
        .container-box.right-panel-active .overlay-right { transform: translateX(20%); }

        /* Power-On Dark Mode Styles */
        body.power-on {
            background-color: #0b0f19;
        }

        /* CARD DARK MODE */
        .power-on .form-card-bg {
            background-color: #0f172a !important;
            color: #f8fafc;
        }

        .power-on .form-title {
            color: #ffffff !important;
        }

        .power-on .form-subtitle {
            color: #94a3b8 !important;
        }

        /* ROTATE ANIMATION UNTUK BORDER NEON */
        @keyframes rotateBorder {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* NEON CARD BORDER */
        .neon-border-wrapper {
            position: relative;
            border-radius: 1.75rem;
            padding: 3px;
            overflow: hidden;
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
                #6366f1 310deg,
                #10b981 360deg
            );
            animation: rotateBorder 4s linear infinite;
            z-index: 0;
        }

        .power-on .container-box {
            box-shadow: 0 0 35px rgba(16, 185, 129, 0.2), 0 0 15px rgba(99, 102, 241, 0.25);
        }

        /* NEON BORDER KELILING INPUT FIELD */
        .input-neon-wrapper {
            position: relative;
            border-radius: 0.75rem;
            padding: 2px;
            overflow: hidden;
            width: 100%;
        }

        .power-on .input-neon-wrapper::before {
            content: '';
            position: absolute;
            top: -100%;
            left: -100%;
            width: 300%;
            height: 300%;
            background: conic-gradient(
                transparent 0deg,
                transparent 270deg,
                #3b82f6 300deg,
                #10b981 360deg
            );
            animation: rotateBorder 3s linear infinite;
            z-index: 0;
        }

        /* INPUT FIELD CYBER STYLE */
        .cyber-input {
            position: relative;
            z-index: 1;
            width: 100%;
            border-radius: 0.65rem;
        }

        .power-on .cyber-input {
            background-color: #0f172a !important;
            border: none !important;
            color: #f8fafc !important;
        }

        .power-on .cyber-input:focus {
            box-shadow: 0 0 10px rgba(16, 185, 129, 0.4);
        }

        .power-on .cyber-input::placeholder {
            color: #64748b;
        }

        /* ROBOT HOVERING & SHADOW ANIMATION */
        @keyframes floatDrone {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-8px) rotate(-2deg); }
        }

        @keyframes shadowScale {
            0%, 100% { transform: scale(1); opacity: 0.3; }
            50% { transform: scale(0.7); opacity: 0.15; }
        }

        .drone-floating {
            animation: floatDrone 3.5s ease-in-out infinite;
        }

        .drone-shadow {
            animation: shadowScale 3.5s ease-in-out infinite;
        }
    </style>
</head>
<body class="bg-slate-100 flex justify-center items-center h-screen overflow-hidden relative" id="main-body">

    <!-- Container Utama Gabungan Robot + Form -->
    <div class="relative flex items-center justify-center">

        <!-- MASKOT ROBOT CYBER DRONE (Ditambahkan transition-all duration-500 ease-in-out) -->
        <div onclick="togglePower()" class="drone-floating absolute -top-14 right-6 z-50 cursor-pointer group flex flex-col items-center transition-all duration-500 ease-in-out">
            
            <div id="robot-speech" class="bg-slate-900/90 backdrop-blur-md text-white text-[11px] font-semibold px-3.5 py-1.5 rounded-full shadow-xl mb-2 border border-indigo-500/40 group-hover:scale-105 transition-all duration-500 flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-indigo-400 animate-ping"></span>
                <span id="speech-text">Klik untuk pindah ke Dark Mode</span>
            </div>

            <div id="robot-body" class="relative w-16 h-16 bg-gradient-to-b from-white to-slate-100 rounded-3xl shadow-xl border-2 border-indigo-200/80 flex flex-col items-center justify-center transition-all duration-500 ease-in-out group-hover:border-indigo-500 group-hover:shadow-indigo-400/40">
                <div class="absolute -top-2 flex justify-between w-8">
                    <div id="ant-1" class="w-1 h-2.5 bg-slate-300 rounded-full transition-all duration-500"></div>
                    <div id="ant-2" class="w-1 h-2.5 bg-slate-300 rounded-full transition-all duration-500"></div>
                </div>

                <div id="robot-visor" class="w-11 h-7 bg-slate-900 rounded-2xl border border-slate-700/60 flex items-center justify-center gap-1.5 shadow-inner transition-all duration-500">
                    <div id="eye-left" class="w-2.5 h-2.5 rounded-full bg-indigo-400 shadow-[0_0_8px_#818cf8] transition-all duration-500"></div>
                    <div id="eye-right" class="w-2.5 h-2.5 rounded-full bg-indigo-400 shadow-[0_0_8px_#818cf8] transition-all duration-500"></div>
                </div>

                <div id="pod-left" class="absolute -left-1.5 w-1.5 h-4 bg-indigo-200 rounded-l-md transition-all duration-500"></div>
                <div id="pod-right" class="absolute -right-1.5 w-1.5 h-4 bg-indigo-200 rounded-r-md transition-all duration-500"></div>
            </div>

            <div class="drone-shadow w-10 h-1.5 bg-slate-900 rounded-full mt-2 blur-[2px]"></div>
        </div>

        <!-- WRAPPER CARD NEON -->
        <div class="neon-border-wrapper">
            
            <!-- FORM CONTAINER -->
            <div class="container-box relative bg-white rounded-[24px] shadow-2xl overflow-hidden w-[850px] max-w-full min-h-[580px] transition-all duration-500 z-10" id="container">
                
                <!-- Status Server Badge -->
                <div class="absolute top-5 left-6 z-40 flex items-center gap-2 px-3 py-1.5 rounded-full bg-slate-100 border border-slate-200 text-[10px] font-mono-code transition-all duration-500" id="server-status-badge">
                    <span class="w-2 h-2 rounded-full bg-slate-400 transition-all duration-500" id="status-dot"></span>
                    <span class="text-slate-600 font-semibold tracking-wider" id="status-text">Status : Clear Mode</span>
                </div>

                <!-- FORM LOGIN -->
                <div class="sign-in-container absolute top-0 left-0 w-1/2 h-full z-2 transition-all duration-600 ease-in-out">
                    <form action="../../controllers/auth_controller.php?action=login" method="POST" class="form-card-bg bg-white flex flex-col justify-center items-center px-12 h-full text-center transition-all duration-500">
                        <h1 class="form-title text-3xl font-bold text-gray-800 mb-1 mt-4 transition-colors">Masuk</h1>
                        <span class="form-subtitle text-xs text-gray-500 mb-4 transition-colors">Silakan Login ke CodeProcess</span>
                        
                        <?php if (isset($_GET['status'])): ?>
                            <?php if ($_GET['status'] === 'registered'): ?>
                                <p class="text-xs text-green-600 bg-green-50 px-3 py-2 rounded-lg mb-3 w-full">Akun berhasil dibuat! Silakan login.</p>
                            <?php elseif ($_GET['status'] === 'wrong_credentials'): ?>
                                <p class="text-xs text-red-600 bg-red-50 px-3 py-2 rounded-lg mb-3 w-full">Email atau password salah.</p>
                            <?php endif; ?>
                        <?php endif; ?>

                        <div class="w-full space-y-3">
                            <div class="input-neon-wrapper">
                                <input type="email" name="email" placeholder="Email" class="cyber-input bg-gray-100 border border-gray-200 px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-all" required />
                            </div>
                            <div class="input-neon-wrapper">
                                <input type="password" name="password" placeholder="Password" class="cyber-input bg-gray-100 border border-gray-200 px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-all" required />
                            </div>
                        </div>
                        
                        <a href="#" class="form-subtitle text-xs text-gray-500 hover:text-indigo-400 my-4 transition-colors">Lupa Password?</a>
                        
                        <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold px-10 py-3 rounded-full text-xs tracking-wider uppercase active:scale-95 transition-all shadow-md hover:shadow-indigo-200">
                            Log In
                        </button>
                    </form>
                </div>

                <!-- FORM SIGN UP -->
                <div class="sign-up-container absolute top-0 left-1/2 w-1/2 h-full z-1 transition-all duration-600 ease-in-out">
                    <form action="../../controllers/auth_controller.php?action=register" method="POST" class="form-card-bg bg-white flex flex-col justify-center items-center px-10 h-full text-center py-6 transition-all duration-500">
                        <h1 class="form-title text-3xl font-bold text-gray-800 mb-1 mt-4 transition-colors">Buat Akun</h1>
                        <span class="form-subtitle text-xs text-gray-500 mb-3 transition-colors">Silakan pilih role dan daftarkan diri Anda</span>
                     
                        <input type="hidden" name="role" id="selected-role" value="student">

                        <div class="flex gap-2 w-full mb-3">
                            <button type="button" id="btn-siswa" onclick="setRole('siswa')" class="role-btn flex-1 py-2 text-xs font-semibold rounded-xl border border-indigo-600 bg-indigo-600 text-white transition-all flex items-center justify-center gap-2 shadow-sm">
                                <i class="fa-solid fa-user-graduate"></i> Siswa
                            </button>
                            <button type="button" id="btn-guru" onclick="setRole('guru')" class="role-btn flex-1 py-2 text-xs font-semibold rounded-xl border border-gray-200 bg-gray-100 text-gray-600 hover:bg-gray-200 transition-all flex items-center justify-center gap-2">
                                <i class="fa-solid fa-chalkboard-user"></i> Lecturer
                            </button>
                        </div>

                        <div class="w-full space-y-2.5">
                            <div class="input-neon-wrapper">
                                <input type="email" name="email" placeholder="Email" class="cyber-input bg-gray-100 border border-gray-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-all" required />
                            </div>
                            <div class="input-neon-wrapper">
                                <input type="text" name="name" placeholder="Nama Lengkap / Username" class="cyber-input bg-gray-100 border border-gray-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-all" required />
                            </div>
                            <div class="input-neon-wrapper" id="wrap-nim">
                                <input type="text" name="identity_number" id="input-nim" placeholder="NIM (Nomor Induk Mahasiswa/Siswa)" class="cyber-input bg-gray-100 border border-gray-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-all" required />
                            </div>
                            <div class="input-neon-wrapper hidden" id="wrap-nig">
                                <input type="text" id="input-nig" placeholder="NIG (No. Induk Guru/Lecturer)" class="cyber-input bg-gray-100 border border-gray-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-all" />
                            </div>
                            <div class="input-neon-wrapper">
                                <input type="password" name="password" placeholder="Password" class="cyber-input bg-gray-100 border border-gray-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-all" required />
                            </div>
                        </div>
                        
                        <button type="submit" class="mt-4 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold px-10 py-3 rounded-full text-xs tracking-wider uppercase active:scale-95 transition-all shadow-md hover:shadow-indigo-200">
                            Sign Up
                        </button>
                    </form>
                </div>

                <!-- OVERLAY CONTAINER -->
                <div class="overlay-container absolute top-0 left-1/2 w-1/2 h-full overflow-hidden transition-transform duration-600 ease-in-out z-30">
                    <div class="overlay overlay-bg text-white relative -left-full h-full w-[200%] translate-x-0 transition-transform duration-600 ease-in-out">
                        
                        <div class="overlay-panel overlay-left absolute top-0 left-0 w-1/2 h-full flex flex-col justify-center items-center px-10 text-center -translate-x-[20%] transition-transform duration-600 ease-in-out">
                            <h1 class="text-3xl font-bold mb-3">Sudah Punya Akun?</h1>
                            <p class="text-sm font-light leading-relaxed mb-8">Masuk sekarang untuk melanjutkan sesi ujian atau memantau progres penilaian.</p>
                            <button id="signIn" class="border-2 border-white text-white font-semibold px-10 py-3 rounded-full text-xs tracking-wider uppercase hover:bg-white hover:text-indigo-600 active:scale-95 transition-all">
                                Login
                            </button>
                        </div>

                        <div class="overlay-panel overlay-right absolute top-0 right-0 w-1/2 h-full flex flex-col justify-center items-center px-10 text-center translate-x-0 transition-transform duration-600 ease-in-out">
                            <h1 class="text-3xl font-bold mb-3">Selamat Datang!</h1>
                            <p class="text-sm font-light leading-relaxed mb-8">Daftarkan akun baru untuk mulai menggunakan platform penilaian koding CodeProcess.</p>
                            <button id="signUp" class="border-2 border-white text-white font-semibold px-10 py-3 rounded-full text-xs tracking-wider uppercase hover:bg-white hover:text-indigo-600 active:scale-95 transition-all">
                                Sign Up
                            </button>
                        </div>

                    </div>
                </div>

            </div>
        </div>

    </div>

    <script>
        const signUpButton = document.getElementById('signUp');
        const signInButton = document.getElementById('signIn');
        const container = document.getElementById('container');
        const mainBody = document.getElementById('main-body');
        const statusDot = document.getElementById('status-dot');
        const statusText = document.getElementById('status-text');
        const statusBadge = document.getElementById('server-status-badge');

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

        // Toggle Dual Panel Slide
        signUpButton.addEventListener('click', () => {
            container.classList.add("right-panel-active");
        });

        signInButton.addEventListener('click', () => {
            container.classList.remove("right-panel-active");
        });

        // Toggle Power / Dark Mode
        function togglePower() {
            isPowerOn = !isPowerOn;

            if (isPowerOn) {
                // Background & Mode
                mainBody.classList.add('power-on');

                // Status Badge Update
                statusDot.className = "w-2 h-2 rounded-full bg-emerald-400 animate-pulse shadow-[0_0_8px_#34d399] transition-all duration-500";
                statusText.textContent = "Status : Dark Mode";
                statusText.className = "text-emerald-400 font-semibold tracking-wider transition-all duration-500";
                statusBadge.className = "absolute top-5 left-6 z-40 flex items-center gap-2 px-3 py-1.5 rounded-full bg-slate-900/90 border border-emerald-500/40 text-[10px] font-mono-code transition-all duration-500 shadow-lg";

                // Drone Robot Glow Mode (Konsisten ukuran border)
                robotBody.className = "relative w-16 h-16 bg-slate-900 rounded-3xl shadow-[0_0_25px_rgba(16,185,129,0.4)] border-2 border-emerald-400 flex flex-col items-center justify-center transition-all duration-500 ease-in-out";
                robotVisor.className = "w-11 h-7 bg-black rounded-2xl border border-emerald-500/50 flex items-center justify-center gap-1.5 shadow-[inset_0_0_10px_rgba(16,185,129,0.3)] transition-all duration-500";
                
                eyeLeft.className = "w-2.5 h-2.5 rounded-full bg-emerald-400 shadow-[0_0_10px_#34d399] animate-pulse transition-all duration-500";
                eyeRight.className = "w-2.5 h-2.5 rounded-full bg-emerald-400 shadow-[0_0_10px_#34d399] animate-pulse transition-all duration-500";
                
                ant1.className = "w-1 h-2.5 bg-emerald-400 shadow-[0_0_6px_#34d399] rounded-full transition-all duration-500";
                ant2.className = "w-1 h-2.5 bg-emerald-400 shadow-[0_0_6px_#34d399] rounded-full transition-all duration-500";

                podLeft.className = "absolute -left-1.5 w-1.5 h-4 bg-emerald-500 rounded-l-md transition-all duration-500 shadow-[0_0_6px_#34d399]";
                podRight.className = "absolute -right-1.5 w-1.5 h-4 bg-emerald-500 rounded-r-md transition-all duration-500 shadow-[0_0_6px_#34d399]";

                speechText.textContent = "Klik untuk pindah ke Clear Mode";
            } else {
                // Reset Dark Mode
                mainBody.classList.remove('power-on');

                // Reset Status Badge
                statusDot.className = "w-2 h-2 rounded-full bg-slate-400 transition-all duration-500";
                statusText.textContent = "Status : Clear Mode";
                statusText.className = "text-slate-600 font-semibold tracking-wider transition-all duration-500";
                statusBadge.className = "absolute top-5 left-6 z-40 flex items-center gap-2 px-3 py-1.5 rounded-full bg-slate-100 border border-slate-200 text-[10px] font-mono-code transition-all duration-500";

                // Reset Drone Robot Appearance
                robotBody.className = "relative w-16 h-16 bg-gradient-to-b from-white to-slate-100 rounded-3xl shadow-xl border-2 border-indigo-200/80 flex flex-col items-center justify-center transition-all duration-500 ease-in-out group-hover:border-indigo-500 group-hover:shadow-indigo-400/40";
                robotVisor.className = "w-11 h-7 bg-slate-900 rounded-2xl border border-slate-700/60 flex items-center justify-center gap-1.5 shadow-inner transition-all duration-500";
                
                eyeLeft.className = "w-2.5 h-2.5 rounded-full bg-indigo-400 shadow-[0_0_8px_#818cf8] transition-all duration-500";
                eyeRight.className = "w-2.5 h-2.5 rounded-full bg-indigo-400 shadow-[0_0_8px_#818cf8] transition-all duration-500";

                ant1.className = "w-1 h-2.5 bg-slate-300 rounded-full transition-all duration-500";
                ant2.className = "w-1 h-2.5 bg-slate-300 rounded-full transition-all duration-500";

                podLeft.className = "absolute -left-1.5 w-1.5 h-4 bg-indigo-200 rounded-l-md transition-all duration-500";
                podRight.className = "absolute -right-1.5 w-1.5 h-4 bg-indigo-200 rounded-r-md transition-all duration-500";

                speechText.textContent = "Klik untuk pindah ke Dark Mode";
            }
        }

        // Switch Role (Siswa / Lecturer)
        function setRole(role) {
            const btnSiswa = document.getElementById('btn-siswa');
            const btnGuru = document.getElementById('btn-guru');
            const wrapNim = document.getElementById('wrap-nim');
            const wrapNig = document.getElementById('wrap-nig');
            const inputNim = document.getElementById('input-nim');
            const inputNig = document.getElementById('input-nig');
            const selectedRole = document.getElementById('selected-role');

            selectedRole.value = (role === 'siswa') ? 'student' : 'lecturer';

            if (role === 'siswa') {
                btnSiswa.className = "role-btn flex-1 py-2 text-xs font-semibold rounded-xl border border-indigo-600 bg-indigo-600 text-white transition-all flex items-center justify-center gap-2 shadow-sm";
                btnGuru.className = "role-btn flex-1 py-2 text-xs font-semibold rounded-xl border border-gray-200 bg-gray-100 text-gray-600 hover:bg-gray-200 transition-all flex items-center justify-center gap-2";
                
                wrapNim.classList.remove('hidden');
                inputNim.name = 'identity_number';
                inputNim.required = true;

                wrapNig.classList.add('hidden');
                inputNig.removeAttribute('name');
                inputNig.required = false;
            } else if (role === 'guru') {
                btnGuru.className = "role-btn flex-1 py-2 text-xs font-semibold rounded-xl border border-indigo-600 bg-indigo-600 text-white transition-all flex items-center justify-center gap-2 shadow-sm";
                btnSiswa.className = "role-btn flex-1 py-2 text-xs font-semibold rounded-xl border border-gray-200 bg-gray-100 text-gray-600 hover:bg-gray-200 transition-all flex items-center justify-center gap-2";

                wrapNig.classList.remove('hidden');
                inputNig.name = 'identity_number';
                inputNig.required = true;

                wrapNim.classList.add('hidden');
                inputNim.removeAttribute('name');
                inputNim.required = false;
            }
        }
    </script>
</body>
</html>