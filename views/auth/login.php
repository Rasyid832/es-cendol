<?php
session_start();
// Jika user sudah login, langsung lempar ke dashboard masing-masing
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'student') header("Location: ../student/dashboard.php");
    elseif ($_SESSION['role'] === 'lecturer') header("Location: ../lecturer/dashboard.php");
    elseif ($_SESSION['role'] === 'admin') header("Location: ../admin/dashboard.php");
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
    <!-- Font Awesome untuk Icon -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts: Poppins -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        body { font-family: 'Poppins', sans-serif; }

        .overlay-bg {
            background-image: linear-gradient(rgba(79, 70, 229, 0.7), rgba(49, 46, 129, 0.8)), url('../../public/images/try2.jpg');
            background-size: cover;
            background-position: center;
        }

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
    </style>
</head>
<body class="bg-slate-100 flex justify-center items-center h-screen overflow-hidden">

    <div class="container-box relative bg-white rounded-3xl shadow-2xl overflow-hidden w-[850px] max-w-full min-h-[550px]" id="container">
        
        <!-- FORM LOGIN -->
        <div class="sign-in-container absolute top-0 left-0 w-1/2 h-full z-2 transition-all duration-600 ease-in-out">
            <form action="../../controllers/auth_controller.php?action=login" method="POST" class="bg-white flex flex-col justify-center items-center px-12 h-full text-center">
                <h1 class="text-3xl font-bold text-gray-800 mb-2">Masuk</h1>
                <span class="text-xs text-gray-500 mb-4">Silakan Login ke CodeProcess</span>
                
                <!-- Notifikasi Pesan -->
                <?php if (isset($_GET['status'])): ?>
                    <?php if ($_GET['status'] === 'registered'): ?>
                        <p class="text-xs text-green-600 bg-green-50 px-3 py-2 rounded-lg mb-3 w-full">Akun berhasil dibuat! Silakan login.</p>
                    <?php elseif ($_GET['status'] === 'wrong_credentials'): ?>
                        <p class="text-xs text-red-600 bg-red-50 px-3 py-2 rounded-lg mb-3 w-full">Email atau password salah.</p>
                    <?php endif; ?>
                <?php endif; ?>

                <div class="w-full space-y-3">
                    <input type="email" name="email" placeholder="Email" class="w-full bg-gray-100 border border-gray-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-all" required />
                    <input type="password" name="password" placeholder="Password" class="w-full bg-gray-100 border border-gray-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-all" required />
                </div>
                
                <a href="#" class="text-xs text-gray-500 hover:text-indigo-600 my-4 transition-colors">Lupa Password?</a>
                
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold px-10 py-3 rounded-full text-xs tracking-wider uppercase active:scale-95 transition-all shadow-md hover:shadow-indigo-200">
                    Log In
                </button>
            </form>
        </div>

        <!-- FORM SIGN UP -->
        <div class="sign-up-container absolute top-0 left-1/2 w-1/2 h-full z-1 transition-all duration-600 ease-in-out">
            <form action="../../controllers/auth_controller.php?action=register" method="POST" class="bg-white flex flex-col justify-center items-center px-10 h-full text-center">
                <h1 class="text-3xl font-bold text-gray-800 mb-1">Buat Akun</h1>
                <span class="text-xs text-gray-500 mb-3">Silakan pilih role dan daftarkan diri Anda</span>
             
                <!-- Hidden Input Role untuk PHP Controller -->
                <input type="hidden" name="role" id="selected-role" value="siswa">

                <div class="flex gap-2 w-full mb-3">
                    <button type="button" id="btn-siswa" onclick="setRole('siswa')" class="role-btn flex-1 py-2 text-xs font-semibold rounded-xl border border-indigo-600 bg-indigo-600 text-white transition-all flex items-center justify-center gap-2 shadow-sm">
                        <i class="fa-solid fa-user-graduate"></i> Siswa
                    </button>
                    <button type="button" id="btn-guru" onclick="setRole('guru')" class="role-btn flex-1 py-2 text-xs font-semibold rounded-xl border border-gray-200 bg-gray-100 text-gray-600 hover:bg-gray-200 transition-all flex items-center justify-center gap-2">
                        <i class="fa-solid fa-chalkboard-user"></i> Lecturer
                    </button>
                </div>

                <div class="w-full space-y-3">
                    <input type="email" name="email" placeholder="Email" class="w-full bg-gray-100 border border-gray-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-all" required />
                    <input type="text" name="identity_number" id="input-nim" placeholder="NIM (Nomor Induk Mahasiswa/Siswa)" class="w-full bg-gray-100 border border-gray-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-all" required />
                    <input type="text" id="input-nig" placeholder="NIG (No. Induk Guru/Lecturer)" class="hidden w-full bg-gray-100 border border-gray-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-all" />
                    <input type="password" name="password" placeholder="Password" class="w-full bg-gray-100 border border-gray-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-all" required />
                </div>
                
                <button type="submit" class="mt-4 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold px-10 py-3 rounded-full text-xs tracking-wider uppercase active:scale-95 transition-all shadow-md hover:shadow-indigo-200">
                    Sign Up
                </button>
            </form>
        </div>

        <!-- OVERLAY CONTAINER -->
        <div class="overlay-container absolute top-0 left-1/2 w-1/2 h-full overflow-hidden transition-transform duration-600 ease-in-out z-50">
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

    <script>
        const signUpButton = document.getElementById('signUp');
        const signInButton = document.getElementById('signIn');
        const container = document.getElementById('container');

        signUpButton.addEventListener('click', () => {
            container.classList.add("right-panel-active");
        });

        signInButton.addEventListener('click', () => {
            container.classList.remove("right-panel-active");
        });

        function setRole(role) {
            const btnSiswa = document.getElementById('btn-siswa');
            const btnGuru = document.getElementById('btn-guru');
            const inputNim = document.getElementById('input-nim');
            const inputNig = document.getElementById('input-nig');
            const selectedRole = document.getElementById('selected-role');

            selectedRole.value = role;

            if (role === 'siswa') {
                btnSiswa.className = "role-btn flex-1 py-2 text-xs font-semibold rounded-xl border border-indigo-600 bg-indigo-600 text-white transition-all flex items-center justify-center gap-2 shadow-sm";
                btnGuru.className = "role-btn flex-1 py-2 text-xs font-semibold rounded-xl border border-gray-200 bg-gray-100 text-gray-600 hover:bg-gray-200 transition-all flex items-center justify-center gap-2";
                
                inputNim.classList.remove('hidden');
                inputNim.name = 'identity_number';
                inputNim.required = true;

                inputNig.classList.add('hidden');
                inputNig.removeAttribute('name');
                inputNig.required = false;
            } else if (role === 'guru') {
                btnGuru.className = "role-btn flex-1 py-2 text-xs font-semibold rounded-xl border border-indigo-600 bg-indigo-600 text-white transition-all flex items-center justify-center gap-2 shadow-sm";
                btnSiswa.className = "role-btn flex-1 py-2 text-xs font-semibold rounded-xl border border-gray-200 bg-gray-100 text-gray-600 hover:bg-gray-200 transition-all flex items-center justify-center gap-2";

                inputNig.classList.remove('hidden');
                inputNig.name = 'identity_number';
                inputNig.required = true;

                inputNim.classList.add('hidden');
                inputNim.removeAttribute('name');
                inputNim.required = false;
            }
        }
    </script>
</body>
</html>