<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'lecturer') {
    header("Location: ../auth/login.php");
    exit();
}

require_once '../../config/db.php';

$lecturer_id = $_SESSION['user_id'];
$rooms_history = [];

try {
    // Mengambil semua room milik dosen
    $sql = "SELECT 
                r.id,
                r.subject_name,
                r.room_code,
                r.created_at,
                r.status,
                COUNT(DISTINCT s.id) AS total_peserta,
                COUNT(DISTINCT CASE 
                    WHEN (
                        SELECT COUNT(*)
                        FROM logs l2
                        WHERE l2.session_id = s.id
                    ) >= 3
                    THEN s.id
                END) AS total_problem
            FROM rooms r
            LEFT JOIN sessions s ON r.id = s.room_id
            LEFT JOIN logs l ON s.id = l.session_id
            WHERE r.lecturer_id = :lecturer_id
            GROUP BY r.id
            ORDER BY r.created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['lecturer_id' => $lecturer_id]);
    $rooms_history = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error_message = "Gagal mengambil data history: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>History Ujian - CodeProcess</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Fira+Code:wght@400;600&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; transition: background-color 0.5s ease, color 0.5s ease; }
        .font-mono-code { font-family: 'Fira Code', monospace; }
        @keyframes rotateBorder { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        body.power-on { background-color: #0b0f19 !important; color: #f8fafc; }
        .neon-border-wrapper { position: relative; border-radius: 1.5rem; padding: 2px; overflow: hidden; transition: all 0.5s ease; }
        .power-on .neon-border-wrapper::before { content: ''; position: absolute; top: -50%; left: -50%; width: 200%; height: 200%; background: conic-gradient(transparent 0deg, transparent 280deg, #6366f1 310deg, #10b981 360deg); animation: rotateBorder 4s linear infinite; z-index: 0; }
        .cyber-card { position: relative; z-index: 1; border-radius: 1.4rem; transition: background-color 0.5s ease, border-color 0.5s ease, box-shadow 0.5s ease; }
        .power-on .cyber-card { background-color: #0f172a !important; border-color: transparent !important; box-shadow: 0 0 25px rgba(16, 185, 129, 0.15), 0 0 10px rgba(99, 102, 241, 0.2); }
        .power-on .cyber-thead { background-color: #0b0f19 !important; color: #94a3b8 !important; border-color: #1e293b !important; }
        .power-on .cyber-tr { border-color: #1e293b !important; }
        .power-on .cyber-tr:hover { background-color: rgba(30, 41, 59, 0.5) !important; }
        .power-on .text-main-title { color: #ffffff !important; }
        .power-on .text-sub-title { color: #94a3b8 !important; }
        @keyframes floatDrone { 0%, 100% { transform: translateY(0px) rotate(0deg); } 50% { transform: translateY(-8px) rotate(-2deg); } }
        @keyframes shadowScale { 0%, 100% { transform: scale(1); opacity: 0.3; } 50% { transform: scale(0.7); opacity: 0.15; } }
        .drone-floating { animation: floatDrone 3.5s ease-in-out infinite; }
        .drone-shadow { animation: shadowScale 3.5s ease-in-out infinite; }
    </style>
</head>
<body class="bg-slate-100 text-slate-800 min-h-screen flex" id="main-body">

    <?php if (file_exists('sidebar.php')) include 'sidebar.php'; ?>

    <main class="flex-1 p-8 space-y-6 relative max-w-6xl mx-auto">
        <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4 border-b border-slate-200 pb-5 transition-colors" id="header-border">
            <div>
                <div class="flex items-center gap-3 mb-1">
                    <h2 class="text-2xl font-bold text-slate-900 tracking-tight text-main-title transition-colors">History & Laporan Ujian</h2>
                    <div class="flex items-center gap-2 px-3 py-1 rounded-full bg-slate-200 border border-slate-300 text-[10px] font-mono-code transition-all duration-500" id="server-status-badge">
                        <span class="w-2 h-2 rounded-full bg-slate-400 transition-all duration-500" id="status-dot"></span>
                        <span class="text-slate-600 font-semibold tracking-wider" id="status-text">Status : Clear Mode</span>
                    </div>
                </div>
                <p class="text-xs text-slate-500 text-sub-title transition-colors">Daftar room ujian aktif dan riwayat ujian terbaru.</p>
            </div>

            <div onclick="togglePower()" class="drone-floating cursor-pointer group flex flex-col items-center transition-all duration-500 ease-in-out self-end md:self-auto">
                <div id="robot-speech" class="bg-slate-900/90 backdrop-blur-md text-white text-[11px] font-semibold px-3.5 py-1.5 rounded-full shadow-xl mb-2 border border-indigo-500/40 group-hover:scale-105 transition-all duration-500 flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-indigo-400 animate-ping"></span>
                    <span id="speech-text">Klik untuk pindah ke Dark Mode</span>
                </div>
                <div id="robot-body" class="relative w-14 h-14 bg-gradient-to-b from-white to-slate-100 rounded-3xl shadow-xl border-2 border-indigo-200/80 flex flex-col items-center justify-center transition-all duration-500 ease-in-out group-hover:border-indigo-500 group-hover:shadow-indigo-400/40">
                    <div class="absolute -top-2 flex justify-between w-7">
                        <div id="ant-1" class="w-1 h-2 bg-slate-300 rounded-full transition-all duration-500"></div>
                        <div id="ant-2" class="w-1 h-2 bg-slate-300 rounded-full transition-all duration-500"></div>
                    </div>
                    <div id="robot-visor" class="w-10 h-6 bg-slate-900 rounded-2xl border border-slate-700/60 flex items-center justify-center gap-1.5 shadow-inner transition-all duration-500">
                        <div id="eye-left" class="w-2 h-2 rounded-full bg-indigo-400 shadow-[0_0_8px_#818cf8] transition-all duration-500"></div>
                        <div id="eye-right" class="w-2 h-2 rounded-full bg-indigo-400 shadow-[0_0_8px_#818cf8] transition-all duration-500"></div>
                    </div>
                    <div id="pod-left" class="absolute -left-1.5 w-1.5 h-3.5 bg-indigo-200 rounded-l-md transition-all duration-500"></div>
                    <div id="pod-right" class="absolute -right-1.5 w-1.5 h-3.5 bg-indigo-200 rounded-r-md transition-all duration-500"></div>
                </div>
                <div class="drone-shadow w-9 h-1.5 bg-slate-900 rounded-full mt-1.5 blur-[2px]"></div>
            </div>
        </div>

        <?php if (!empty($error_message)): ?>
            <div class="bg-rose-500/10 border border-rose-500/30 text-rose-400 p-4 rounded-xl text-xs flex items-center gap-3">
                <i class="fa-solid fa-circle-exclamation text-base shrink-0"></i>
                <span><?= htmlspecialchars($error_message) ?></span>
            </div>
        <?php endif; ?>

        <div class="neon-border-wrapper">
            <div class="cyber-card bg-white border border-slate-200 rounded-2xl overflow-hidden shadow-xl">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="cyber-thead bg-slate-50 text-xs text-slate-500 uppercase border-b border-slate-200 transition-colors">
                            <tr>
                                <th class="px-6 py-4">Nama Ujian / Room</th>
                                <th class="px-6 py-4">Tanggal Dibuat</th>
                                <th class="px-6 py-4 text-center">Total Peserta</th>
                                <th class="px-6 py-4 text-center">Indikasi Problem</th>
                                <th class="px-6 py-4 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200/80 transition-colors">
                            <?php if (!empty($rooms_history)): ?>
                                <?php foreach ($rooms_history as $room): ?>
                                    <tr class="cyber-tr hover:bg-slate-50/80 transition-colors">
                                        <td class="px-6 py-4">
                                            <p class="font-bold text-main-title transition-colors"><?= htmlspecialchars($room['subject_name']) ?></p>
                                            <p class="text-xs text-indigo-500 font-mono-code mt-0.5">ID: <?= htmlspecialchars($room['room_code']) ?></p>
                                        </td>
                                        <td class="px-6 py-4 text-sub-title text-xs transition-colors">
                                            <?= date('d M Y, H:i', strtotime($room['created_at'])) ?>
                                        </td>
                                        <td class="px-6 py-4 text-center font-mono-code text-xs text-sub-title transition-colors">
                                            <?= htmlspecialchars($room['total_peserta']) ?> Siswa
                                        </td>
                                        <td class="px-6 py-4 text-center">
                                            <?php if ($room['total_problem'] > 0): ?>
                                                <span class="inline-flex items-center gap-1.5 font-bold text-rose-500 bg-rose-500/10 border border-rose-500/30 px-3 py-1 rounded-lg text-xs shadow-sm">
                                                    <i class="fa-solid fa-triangle-exclamation"></i> <?= $room['total_problem'] ?> Siswa
                                                </span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center gap-1.5 font-semibold text-emerald-600 bg-emerald-500/10 border border-emerald-500/30 px-3 py-1 rounded-lg text-xs shadow-sm">
                                                    <i class="fa-solid fa-circle-check"></i> Aman (0)
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 text-center">
                                            <div class="flex items-center justify-center gap-2">
                                                <a href="review_history.php?room_id=<?= $room['id'] ?>" class="text-xs font-semibold bg-indigo-600 hover:bg-indigo-500 text-white px-3 py-2 rounded-xl transition-all shadow-md inline-flex items-center gap-1.5">
                                                    <i class="fa-solid fa-magnifying-glass"></i> Cek Bukti
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="px-6 py-12 text-center text-slate-400 text-xs">
                                        <i class="fa-solid fa-folder-open text-3xl mb-2 block opacity-50"></i>
                                        Belum ada riwayat room ujian.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <script>
        const mainBody = document.getElementById('main-body');
        const headerBorder = document.getElementById('header-border');
        const statusDot = document.getElementById('status-dot');
        const statusText = document.getElementById('status-text');
        const statusBadge = document.getElementById('server-status-badge');
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
                mainBody.classList.add('power-on');
                headerBorder.className = "flex flex-col md:flex-row items-start md:items-center justify-between gap-4 border-b border-slate-800/80 pb-5 transition-colors";
                statusDot.className = "w-2 h-2 rounded-full bg-emerald-400 animate-pulse shadow-[0_0_8px_#34d399] transition-all duration-500";
                statusText.textContent = "Status : Dark Mode";
                statusText.className = "text-emerald-400 font-semibold tracking-wider transition-all duration-500";
                statusBadge.className = "flex items-center gap-2 px-3 py-1 rounded-full bg-slate-900/90 border border-emerald-500/40 text-[10px] font-mono-code transition-all duration-500 shadow-lg";
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
                mainBody.classList.remove('power-on');
                headerBorder.className = "flex flex-col md:flex-row items-start md:items-center justify-between gap-4 border-b border-slate-200 pb-5 transition-colors";
                statusDot.className = "w-2 h-2 rounded-full bg-slate-400 transition-all duration-500";
                statusText.textContent = "Status : Clear Mode";
                statusText.className = "text-slate-600 font-semibold tracking-wider transition-all duration-500";
                statusBadge.className = "flex items-center gap-2 px-3 py-1 rounded-full bg-slate-200 border border-slate-300 text-[10px] font-mono-code transition-all duration-500";
                robotBody.className = "relative w-14 h-14 bg-gradient-to-b from-white to-slate-100 rounded-3xl shadow-xl border-2 border-indigo-200/80 flex flex-col items-center justify-center transition-all duration-500 ease-in-out group-hover:border-indigo-500 group-hover:shadow-indigo-400/40";
                robotVisor.className = "w-10 h-6 bg-slate-900 rounded-2xl border border-slate-700/60 flex items-center justify-center gap-1.5 shadow-inner transition-all duration-500";
                eyeLeft.className = "w-2 h-2 rounded-full bg-indigo-400 shadow-[0_0_8px_#818cf8] transition-all duration-500";
                eyeRight.className = "w-2 h-2 rounded-full bg-indigo-400 shadow-[0_0_8px_#818cf8] transition-all duration-500";
                ant1.className = "w-1 h-2 bg-slate-300 rounded-full transition-all duration-500";
                ant2.className = "w-1 h-2 bg-slate-300 rounded-full transition-all duration-500";
                podLeft.className = "absolute -left-1.5 w-1.5 h-3.5 bg-indigo-200 rounded-l-md transition-all duration-500";
                podRight.className = "absolute -right-1.5 w-1.5 h-3.5 bg-indigo-200 rounded-r-md transition-all duration-500";
                speechText.textContent = "Klik untuk pindah ke Dark Mode";
                localStorage.setItem('theme_mode', 'clear');
            }
        }

        if (localStorage.getItem('theme_mode') === 'dark') {
            togglePower();
        }
    </script>
</body>
</html>