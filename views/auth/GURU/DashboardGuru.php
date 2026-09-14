<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

if (($_SESSION['role'] ?? '') !== 'lecturer') {
    header("Location: ../login.php");
    exit();
}

$nama_guru = $_SESSION['name'] ?? 'Nama Guru';
$role = 'Pengajar / Instructor';
$inisial = strtoupper(substr($nama_guru, 0, 2));

$rooms = [
    [
        'id' => 'RM-8492',
        'pw' => 'EXAM2026',
        'tms' => 20,
        'submit' => 16
    ],
    [
        'id' => 'RM-3105',
        'pw' => 'KODE99',
        'tms' => 20,
        'submit' => 16
    ]
];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Guru</title>

    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        body {
            font-family: Arial, sans-serif;
        }

        .profile-menu {
            opacity: 0;
            visibility: hidden;
            transform: translateY(-8px);
            transition: all 0.2s ease;
        }

        .profile-wrapper.active .profile-menu {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }
    </style>
</head>

<body class="bg-slate-50 min-h-screen">

    <header class="bg-white border-b border-slate-200">
        <div class="max-w-7xl mx-auto px-6 py-4 flex items-center justify-between">

            <div class="relative profile-wrapper" id="profileWrapper">

                <button
                    type="button"
                    id="profileButton"
                    class="flex items-center gap-3 px-3 py-2 rounded-xl hover:bg-slate-100 transition">

                    <div class="w-10 h-10 rounded-full bg-indigo-600 text-white flex items-center justify-center font-bold">
                        <?= htmlspecialchars($inisial) ?>
                    </div>

                    <div class="text-left hidden sm:block">
                        <p class="text-sm font-semibold text-slate-800">
                            <?= htmlspecialchars($nama_guru) ?>
                        </p>

                        <p class="text-xs text-slate-500">
                            <?= htmlspecialchars($role) ?>
                        </p>
                    </div>

                    <svg
                        class="w-4 h-4 text-slate-500 transition-transform"
                        id="profileArrow"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24">

                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M19 9l-7 7-7-7" />
                    </svg>

                </button>

                <div
                    class="profile-menu absolute left-0 mt-2 w-56 bg-white border border-slate-200 rounded-xl shadow-lg z-50">

                    <div class="px-4 py-3 border-b border-slate-100">
                        <p class="text-sm font-semibold text-slate-800">
                            <?= htmlspecialchars($nama_guru) ?>
                        </p>

                        <p class="text-xs text-slate-500 mt-1">
                            <?= htmlspecialchars($role) ?>
                        </p>
                    </div>

                    <div class="p-2">
                        <a
                            href="../../../controllers/auth_controller.php?action=logout"
                            class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-red-600 hover:bg-red-50 transition">

                            <svg
                                class="w-5 h-5"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24">

                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />

                            </svg>

                            <span class="text-sm font-medium">
                                Logout
                            </span>

                        </a>
                    </div>

                </div>
            </div>

            <div class="text-right">
                <h1 class="text-xl font-bold text-slate-800">
                    Dashboard Guru
                </h1>

                <p class="text-sm text-slate-500">
                    Kelola room ujian dan peserta
                </p>
            </div>

        </div>
    </header>

    <main class="max-w-7xl mx-auto px-6 py-8">

        <div class="mb-8 flex items-center justify-between">

            <div>
                <h2 class="text-2xl font-bold text-slate-800">
                    Selamat datang, <?= htmlspecialchars($nama_guru) ?>!
                </h2>

                <p class="text-slate-500 mt-1">
                    Berikut adalah room ujian yang sedang kamu kelola.
                </p>
            </div>

            <a
                href="BuatRoom.php"
                class="flex items-center gap-2 px-5 py-2.5 rounded-xl bg-indigo-600 text-white font-semibold hover:bg-indigo-700 transition">

                <svg
                    class="w-5 h-5"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24">

                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M12 4v16m8-8H4" />

                </svg>

                Buat Room

            </a>

        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">

            <?php foreach ($rooms as $room): ?>

                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">

                    <div class="flex items-center justify-between mb-5">

                        <div>
                            <p class="text-xs text-slate-400 uppercase tracking-wide">
                                Room Code
                            </p>

                            <h3 class="text-xl font-bold text-slate-800 mt-1">
                                <?= htmlspecialchars($room['id']) ?>
                            </h3>
                        </div>

                        <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center">

                            <svg
                                class="w-5 h-5"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24">

                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v2h8z" />

                            </svg>

                        </div>

                    </div>

                    <div class="bg-slate-50 rounded-xl p-4 mb-5">

                        <div class="flex justify-between items-center">

                            <span class="text-sm text-slate-500">
                                Password
                            </span>

                            <span class="font-semibold text-slate-700">
                                <?= htmlspecialchars($room['pw']) ?>
                            </span>

                        </div>

                    </div>

                    <div class="grid grid-cols-2 gap-3">

                        <div class="bg-indigo-50 rounded-xl p-4">

                            <p class="text-xs text-indigo-500">
                                Peserta
                            </p>

                            <p class="text-xl font-bold text-indigo-700 mt-1">
                                <?= $room['tms'] ?>
                            </p>

                        </div>

                        <div class="bg-emerald-50 rounded-xl p-4">

                            <p class="text-xs text-emerald-500">
                                Submit
                            </p>

                            <p class="text-xl font-bold text-emerald-700 mt-1">
                                <?= $room['submit'] ?>
                            </p>

                        </div>

                    </div>

                    <button
                        type="button"
                        class="w-full mt-5 py-2.5 rounded-xl bg-indigo-600 text-white font-semibold hover:bg-indigo-700 transition">

                        Kelola Room

                    </button>

                </div>

            <?php endforeach; ?>

        </div>

    </main>

    <script>
        const profileWrapper = document.getElementById('profileWrapper');
        const profileButton = document.getElementById('profileButton');
        const profileArrow = document.getElementById('profileArrow');

        profileButton.addEventListener('click', function (event) {
            event.stopPropagation();

            profileWrapper.classList.toggle('active');

            if (profileWrapper.classList.contains('active')) {
                profileArrow.classList.add('rotate-180');
            } else {
                profileArrow.classList.remove('rotate-180');
            }
        });

        document.addEventListener('click', function (event) {
            if (!profileWrapper.contains(event.target)) {
                profileWrapper.classList.remove('active');
                profileArrow.classList.remove('rotate-180');
            }
        });
    </script>

</body>
</html>
