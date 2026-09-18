<?php
session_start();
require_once __DIR__ . '/../../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'lecturer') {
    header("Location: ../auth/login.php");
    exit();
}

$user_name = $_SESSION['name'] ?? 'Dosen';
$lecturer_id = $_SESSION['user_id'];

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

try {
    $stmtRooms = $pdo->prepare("
        SELECT * FROM rooms 
        WHERE lecturer_id = :lecturer_id 
        ORDER BY created_at DESC
    ");
    $stmtRooms->execute(['lecturer_id' => $lecturer_id]);
    $my_rooms = $stmtRooms->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $my_rooms = [];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Dosen - CodeProcess</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Fira+Code:wght@400;600&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        body { font-family: 'Poppins', sans-serif; transition: background-color 0.5s ease, color 0.5s ease; }
        .font-mono-code { font-family: 'Fira Code', monospace; }


        @keyframes rotateBorder {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        body.power-on {
            background-color: #0b0f19 !important;
            color: #f8fafc;
        }

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
                #6366f1 310deg,
                #10b981 360deg
            );
            animation: rotateBorder 4s linear infinite;
            z-index: 0;
        }

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

        .power-on .text-main-title { color: #ffffff !important; }
        .power-on .text-sub-title { color: #94a3b8 !important; }
        .power-on .bg-card-element { background-color: #1e293b !important; border-color: #334155 !important; }

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

    <?php 
    if (file_exists('sidebar.php')) {
        include 'sidebar.php'; 
    }
    ?>

    <main class="flex-1 p-8 max-w-7xl mx-auto space-y-8 relative">

        <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4 border-b border-slate-200 dark:border-slate-800 pb-6 transition-colors" id="header-border">
            <div>
                <div class="flex items-center gap-3 mb-1">
                    <h1 class="text-3xl font-bold text-slate-900 tracking-tight text-main-title transition-colors">
                        Selamat Datang, <?= htmlspecialchars($user_name) ?>!
                    </h1>
                    <div class="flex items-center gap-2 px-3 py-1 rounded-full bg-slate-200 border border-slate-300 text-[10px] font-mono-code transition-all duration-500" id="server-status-badge">
                        <span class="w-2 h-2 rounded-full bg-slate-400 transition-all duration-500" id="status-dot"></span>
                        <span class="text-slate-600 font-semibold tracking-wider" id="status-text">Status : Clear Mode</span>
                    </div>
                </div>
                <p class="text-xs text-slate-500 text-sub-title transition-colors">
                    Kelola room ujian, buat soal baru, dan pantau aktivitas pengerjaan mahasiswa secara real-time.
                </p>
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

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
            <!-- Card 1 -->
            <div class="neon-border-wrapper">
                <div class="cyber-card bg-white p-5 border border-slate-200 flex items-center justify-between">
                    <div>
                        <p class="text-xs text-slate-500 font-semibold text-sub-title">Total Room Ujian</p>
                        <h3 class="text-2xl font-bold text-slate-800 text-main-title mt-1"><?= count($my_rooms) ?></h3>
                    </div>
                    <div class="w-11 h-11 bg-indigo-600/10 text-indigo-600 rounded-2xl flex items-center justify-center text-lg">
                        <i class="fa-solid fa-door-open"></i>
                    </div>
                </div>
            </div>

            <div class="neon-border-wrapper">
                <div class="cyber-card bg-white p-5 border border-slate-200 flex items-center justify-between">
                    <div>
                        <p class="text-xs text-slate-500 font-semibold text-sub-title">Room Aktif</p>
                        <h3 class="text-2xl font-bold text-emerald-500 mt-1">
                            <?= count(array_filter($my_rooms, fn($r) => ($r['status'] ?? 'aktif') === 'active' || ($r['status'] ?? 'aktif') === 'aktif')) ?>
                        </h3>
                    </div>
                    <div class="w-11 h-11 bg-emerald-500/10 text-emerald-500 rounded-2xl flex items-center justify-center text-lg">
                        <i class="fa-solid fa-bolt"></i>
                    </div>
                </div>
            </div>

            <div class="neon-border-wrapper">
                <div class="cyber-card bg-white p-5 border border-slate-200 flex items-center justify-between">
                    <div>
                        <p class="text-xs text-slate-500 font-semibold text-sub-title">Mahasiswa Aktif</p>
                        <h3 class="text-2xl font-bold text-slate-800 text-main-title mt-1">-</h3>
                    </div>
                    <div class="w-11 h-11 bg-blue-500/10 text-blue-500 rounded-2xl flex items-center justify-center text-lg">
                        <i class="fa-solid fa-users"></i>
                    </div>
                </div>
            </div>

            <div class="neon-border-wrapper">
                <div class="cyber-card bg-white p-5 border border-slate-200 flex items-center justify-between">
                    <div>
                        <p class="text-xs text-slate-500 font-semibold text-sub-title">Selesai Dinilai</p>
                        <h3 class="text-2xl font-bold text-slate-800 text-main-title mt-1">-</h3>
                    </div>
                    <div class="w-11 h-11 bg-amber-500/10 text-amber-500 rounded-2xl flex items-center justify-center text-lg">
                        <i class="fa-solid fa-square-check"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="neon-border-wrapper">
            <div class="cyber-card bg-white border border-slate-200 p-6 space-y-6">
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-bold text-slate-800 text-main-title">Daftar Room Ujian</h2>
                        <p class="text-xs text-slate-500 text-sub-title">Kelola room ujian yang telah dibuat atau tambahkan room baru.</p>
                    </div>
                    <a href="create_room.php" class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold px-5 py-2.5 rounded-xl text-xs flex items-center gap-2 transition shadow-md hover:shadow-indigo-500/30">
                        <i class="fa-solid fa-plus"></i> Buat Room Baru
                    </a>
                </div>

                <?php if (empty($my_rooms)): ?>
                    <div class="p-8 rounded-2xl border border-dashed border-slate-300 dark:border-slate-800 text-center space-y-2">
                        <i class="fa-solid fa-folder-open text-3xl text-slate-400"></i>
                        <p class="text-xs text-slate-500">Belum ada room ujian yang dibuat.</p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead>
                                <tr class="border-b border-slate-200 dark:border-slate-800 text-slate-400 font-mono-code uppercase">
                                    <th class="pb-3 px-3">Kode Room</th>
                                    <th class="pb-3 px-3">Mata Kuliah / Ujian</th>
                                    <th class="pb-3 px-3">Kelas</th>
                                    <th class="pb-3 px-3">Durasi</th>
                                    <th class="pb-3 px-3">Status</th>
                                    <th class="pb-3 px-3 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60">
                                <?php foreach ($my_rooms as $r): ?>
                                    <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition">
                                        <td class="py-4 px-3">
                                            <div class="flex items-center gap-2">
                                                <code class="font-mono text-indigo-500 font-bold">
                                                    <?= htmlspecialchars($r['room_code'] ?? '-') ?>
                                                </code>
                                                <button type="button" 
                                                        onclick="copyRoomCode('<?= htmlspecialchars($r['room_code'] ?? '', ENT_QUOTES) ?>', this)" 
                                                        class="text-[10px] bg-slate-100 dark:bg-slate-800 hover:bg-indigo-50 text-slate-600 dark:text-slate-300 px-2 py-0.5 rounded border border-slate-200 dark:border-slate-700 transition flex items-center gap-1">
                                                    <i class="fa-regular fa-copy"></i>
                                                    <span class="copy-label">Salin</span>
                                                </button>
                                            </div>
                                        </td>
                                        <td class="py-4 px-3 font-semibold text-slate-700 text-main-title"><?= htmlspecialchars($r['subject_name'] ?? 'Ujian') ?></td>
                                        <td class="py-4 px-3 text-slate-500 text-sub-title"><?= htmlspecialchars($r['class_name'] ?? 'Umum') ?></td>
                                        
                                        <td class="py-4 px-3 text-slate-500 text-sub-title font-mono"><?= htmlspecialchars($r['duration'] ?? $r['duration_minutes'] ?? '90') ?> Menit</td>
                                        
                                        <td class="py-4 px-3">
                                            <span class="px-2.5 py-1 rounded-full text-[10px] font-semibold bg-emerald-500/10 text-emerald-500 border border-emerald-500/20">
                                                <?= htmlspecialchars($r['status'] ?? 'Aktif') ?>
                                            </span>
                                        </td>
                                        <td class="py-4 px-3 text-right space-x-2">
                                            <a href="room_submissions.php?id=<?= $r['id'] ?? 0 ?>" title="Lihat hasil kerja siswa" class="p-2 bg-indigo-50 dark:bg-indigo-950/50 text-indigo-600 rounded-lg hover:bg-indigo-100 transition inline-block"><i class="fa-solid fa-eye"></i></a>
                                            <a href="edit_room.php?id=<?= $r['id'] ?? 0 ?>" title="Pengaturan room" class="p-2 bg-slate-100 dark:bg-slate-800 text-slate-600 text-sub-title rounded-lg hover:bg-slate-200 transition inline-block"><i class="fa-solid fa-gear"></i></a>
                                            <a href="../../controllers/controller_room_action.php?action=delete&id=<?= $r['id'] ?? 0 ?>&from=dashboard&csrf_token=<?= urlencode($_SESSION['csrf_token']) ?>"
                                               title="Hapus room"
                                               onclick="return confirm('Hapus room \'<?= htmlspecialchars($r['subject_name'] ?? '', ENT_QUOTES) ?>\' secara permanen? Semua data siswa yang mengerjakan di room ini juga akan terhapus. Aksi ini tidak bisa dibatalkan.');"
                                               class="p-2 bg-red-50 dark:bg-red-950/50 text-red-600 rounded-lg hover:bg-red-100 transition inline-block"><i class="fa-solid fa-trash"></i></a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

            </div>
        </div>

    </main>

    <script>
        const mainBody = document.getElementById('main-body');
        const statusDot = document.getElementById('status-dot');
        const statusText = document.getElementById('status-text');
        const statusBadge = document.getElementById('server-status-badge');
        const headerBorder = document.getElementById('header-border');

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
                headerBorder.className = "flex flex-col md:flex-row items-start md:items-center justify-between gap-4 border-b border-slate-800/80 pb-6 transition-colors";

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
                headerBorder.className = "flex flex-col md:flex-row items-start md:items-center justify-between gap-4 border-b border-slate-200 pb-6 transition-colors";

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

        function copyRoomCode(code, buttonEl) {
            navigator.clipboard.writeText(code).then(() => {
                const labelEl = buttonEl.querySelector('.copy-label');
                const iconEl = buttonEl.querySelector('i');
                const originalText = labelEl.textContent;
                const originalIconClass = iconEl.className;
                
                labelEl.textContent = 'Disalin';
                iconEl.className = 'fa-solid fa-check text-emerald-500';

                setTimeout(() => {
                    labelEl.textContent = originalText;
                    iconEl.className = originalIconClass;
                }, 2000);
            });
        }

        if (localStorage.getItem('theme_mode') === 'dark') {
            togglePower();
        }
    </script>
</body>
</html>