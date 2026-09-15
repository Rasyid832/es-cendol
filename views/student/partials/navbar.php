<?php
/**
 * views/student/partials/navbar.php
 * Navbar atas untuk semua halaman role 'student'.
 * Wajib di-include SETELAH session_start() dan variabel $user_name tersedia.
 * Contoh pakai: include __DIR__ . '/partials/navbar.php';
 */
$user_name = $_SESSION['name'] ?? 'Mahasiswa';
$initial   = strtoupper(substr($user_name, 0, 1));
?>
<nav class="flex items-center justify-between px-6 md:px-10 py-4 border-b border-slate-800 bg-[#0d1526]">
    <div class="flex items-center gap-2">
        <div class="w-8 h-8 rounded-lg bg-indigo-600 flex items-center justify-center">
            <i class="fa-solid fa-graduation-cap text-white text-sm"></i>
        </div>
        <span class="font-bold text-lg text-white tracking-tight">ExamPro</span>
    </div>
    <div class="flex items-center gap-3">
        <div class="text-right hidden sm:block">
            <p class="text-sm font-semibold text-white leading-tight"><?= htmlspecialchars($user_name) ?></p>
            <p class="text-[11px] text-slate-400 leading-tight">Mahasiswa</p>
        </div>
        <div class="w-9 h-9 rounded-full bg-indigo-500 flex items-center justify-center text-white text-sm font-semibold">
            <?= htmlspecialchars($initial) ?>
        </div>
        <a href="../../controllers/logout.php" title="Keluar" class="ml-2 text-slate-400 hover:text-red-400 transition">
            <i class="fa-solid fa-right-from-bracket"></i>
        </a>
    </div>
</nav>