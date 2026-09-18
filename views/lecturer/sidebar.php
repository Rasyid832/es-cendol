<?php

$current_page = basename($_SERVER['PHP_SELF']);

$dosen_name = $_SESSION['name'] ?? 'Dosen Pengampu';
$initials = strtoupper(substr($dosen_name, 0, 2));
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<script src="https://cdn.tailwindcss.com"></script>

<aside class="w-[260px] h-screen bg-slate-900 border-r border-slate-800 flex flex-col justify-between p-4 sticky top-0 shrink-0 text-slate-100">
    <div class="flex flex-col gap-6">
        <!-- Logo / Brand -->
        <div class="flex items-center gap-3 px-3 py-2 bg-indigo-600/10 border border-indigo-500/20 rounded-xl">
            <div class="bg-indigo-600 text-white p-2.5 rounded-lg flex items-center justify-center">
                <i class="fa-solid fa-shield-halved text-lg"></i>
            </div>
            <div>
                <h1 class="font-bold text-sm tracking-wide text-white">CodeProcess</h1>
                <p class="text-[10px] text-indigo-400 font-mono">Dosen Panel</p>
            </div>
        </div>

        <!-- Menu Navigasi Utama -->
        <nav class="flex flex-col gap-1.5">
            <a href="dashboard.php" class="flex items-center gap-3 px-3.5 py-3 rounded-xl font-medium text-sm transition-all <?= ($current_page == 'dashboard.php') ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-600/30' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800' ?>">
                <i class="fa-solid fa-house w-5 text-center"></i>
                <span>Dashboard</span>
            </a>

            <a href="create_room.php" class="flex items-center gap-3 px-3.5 py-3 rounded-xl font-medium text-sm transition-all <?= ($current_page == 'create_room.php') ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-600/30' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800' ?>">
                <i class="fa-solid fa-plus-circle w-5 text-center"></i>
                <span>Buat Room Ujian</span>
            </a>

            <a href="join_room.php" class="flex items-center gap-3 px-3.5 py-3 rounded-xl font-medium text-sm transition-all <?= ($current_page == 'join_room.php') ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-600/30' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800' ?>">
                <i class="fa-solid fa-right-to-bracket w-5 text-center"></i>
                <span>Masuk Room Ujian</span>
            </a>

            <a href="history.php" class="flex items-center gap-3 px-3.5 py-3 rounded-xl font-medium text-sm transition-all <?= ($current_page == 'history.php') ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-600/30' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800' ?>">
                <i class="fa-solid fa-clock-rotate-left w-5 text-center"></i>
                <span>History Ujian</span>
            </a>

           
        </nav>
    </div>

    <!-- Profil Dosen & Logout -->
    <div class="border-t border-slate-800 pt-4 flex items-center justify-between px-2">
        <div class="flex items-center gap-3 overflow-hidden">
            <div class="w-8 h-8 rounded-full bg-indigo-600/20 border border-indigo-500/30 flex items-center justify-center text-indigo-400 font-bold text-xs shrink-0">
                <?= $initials ?>
            </div>
            <div class="truncate">
                <p class="text-xs font-bold text-slate-200 leading-tight truncate"><?= htmlspecialchars($dosen_name) ?></p>
                <p class="text-[10px] text-indigo-400">Lecturer</p>
            </div>
        </div>
        <a href="../../controllers/logout.php" class="text-slate-500 hover:text-rose-400 transition-colors p-1.5 rounded-lg hover:bg-slate-800 shrink-0" title="Keluar">
            <i class="fa-solid fa-right-from-bracket text-sm"></i>
        </a>
    </div>
</aside>