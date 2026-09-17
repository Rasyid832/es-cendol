<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

require_once __DIR__ . '/../../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'lecturer') {
    header("Location: ../auth/login.php");
    exit();
}

$room_id = isset($_GET['id']) ? trim($_GET['id']) : ($_SESSION['active_room_id'] ?? null);

if (!$room_id) {
    header("Location: dashboard.php");
    exit();
}

try {
    $stmt_room = $pdo->prepare("SELECT * FROM rooms WHERE id = :id OR room_code = :code LIMIT 1");
    $stmt_room->execute([
        'id' => $room_id,
        'code' => $room_id
    ]);
    $room = $stmt_room->fetch(PDO::FETCH_ASSOC);

    if (!$room) {
        die("Room ujian tidak ditemukan! (ID/Kode: " . htmlspecialchars($room_id) . ")");
    }

    $actual_room_id = $room['id'];

    $stmt_students = $pdo->prepare("
        SELECT
            u.id,
            u.name,
            u.identity_number,
            s.joined_at,
            s.status AS session_status,
            COUNT(t.id) AS warning_count
        FROM sessions s
        JOIN users u ON s.student_id = u.id
        LEFT JOIN telemetry_logs t ON t.session_id = s.id
        WHERE s.room_id = :room_id
        GROUP BY u.id, u.name, u.identity_number, s.joined_at, s.status
        ORDER BY s.joined_at DESC
    ");
    $stmt_students->execute(['room_id' => $actual_room_id]);
    $joined_students = $stmt_students->fetchAll(PDO::FETCH_ASSOC);

    $total_students = count($joined_students);

    $stmt_warning_count = $pdo->prepare("
        SELECT COUNT(t.id)
        FROM telemetry_logs t
        JOIN sessions s ON t.session_id = s.id
        WHERE s.room_id = :room_id
    ");
    $stmt_warning_count->execute(['room_id' => $actual_room_id]);
    $total_warnings = $stmt_warning_count->fetchColumn() ?: 0;

    $stmt_logs = $pdo->prepare("
        SELECT t.*, u.name AS student_name, u.identity_number
        FROM telemetry_logs t
        JOIN sessions s ON t.session_id = s.id
        JOIN users u ON s.student_id = u.id
        WHERE s.room_id = :room_id
        ORDER BY t.submitted_at DESC
        LIMIT 10
    ");
    $stmt_logs->execute(['room_id' => $actual_room_id]);
    $recent_logs = $stmt_logs->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error Database: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengawasan Ujian: <?= htmlspecialchars($room['subject_name'] ?? $room['name'] ?? 'Ujian') ?> - CodeProcess</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Fira+Code:wght@400;600&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <meta http-equiv="refresh" content="15">
    <style>
        body { font-family: 'Poppins', sans-serif; transition: background-color 0.5s ease, color 0.5s ease; }
        .font-mono-code { font-family: 'Fira Code', monospace; }
        @keyframes rotateBorder { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        body.power-on { background-color: #0b0f19 !important; color: #f8fafc; }
        .neon-border-wrapper { position: relative; border-radius: 1.5rem; padding: 2px; overflow: hidden; transition: all 0.5s ease; }
        .power-on .neon-border-wrapper::before { content: ''; position: absolute; top: -50%; left: -50%; width: 200%; height: 200%; background: conic-gradient(transparent 0deg, transparent 280deg, #6366f1 310deg, #10b981 360deg); animation: rotateBorder 4s linear infinite; z-index: 0; }
        .cyber-card { position: relative; z-index: 1; border-radius: 1.4rem; transition: background-color 0.5s ease, border-color 0.5s ease, box-shadow 0.5s ease; }
        .power-on .cyber-card { background-color: #0f172a !important; border-color: transparent !important; box-shadow: 0 0 25px rgba(16, 185, 129, 0.15), 0 0 10px rgba(99, 102, 241, 0.2); }
        .power-on .text-main-title { color: #ffffff !important; }
        .power-on .text-sub-title { color: #94a3b8 !important; }
        @keyframes floatDrone { 0%, 100% { transform: translateY(0px) rotate(0deg); } 50% { transform: translateY(-8px) rotate(-2deg); } }
        @keyframes shadowScale { 0%, 100% { transform: scale(1); opacity: 0.3; } 50% { transform: scale(0.7); opacity: 0.15; } }
        .drone-floating { animation: floatDrone 3.5s ease-in-out infinite; }
        .drone-shadow { animation: shadowScale 3.5s ease-in-out infinite; }
    </style>
</head>
<body class="bg-slate-100 text-slate-800 min-h-screen flex" id="main-body">
    <?php
    $sidebar_path = __DIR__ . '/sidebar.php';
    if (file_exists($sidebar_path)) {
        include $sidebar_path;
    }
    ?>

    <main class="flex-1 p-8 max-w-7xl mx-auto space-y-6 relative">
        <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4 border-b border-slate-200 pb-5 transition-colors" id="header-border">
            <div>
                <div class="flex items-center gap-3 mb-1">
                    <a href="dashboard.php" class="text-xs text-indigo-600 hover:text-indigo-800 font-semibold flex items-center gap-1">
                        <i class="fa-solid fa-arrow-left"></i> Kembali ke Dashboard
                    </a>
                    <div class="flex items-center gap-2 px-3 py-1 rounded-full bg-slate-200 border border-slate-300 text-[10px] font-mono-code transition-all duration-500" id="server-status-badge">
                        <span class="w-2 h-2 rounded-full bg-slate-400 transition-all duration-500" id="status-dot"></span>
                        <span class="text-slate-600 font-semibold tracking-wider" id="status-text">Status : Clear Mode</span>
                    </div>
                </div>
                <h1 class="text-2xl font-bold text-slate-900 tracking-tight text-main-title transition-colors">
                    <?= htmlspecialchars($room['subject_name'] ?? $room['name'] ?? 'Mode Pengawasan Ujian') ?>
                </h1>
                <p class="text-xs text-slate-500 text-sub-title transition-colors">
                    Kode Ujian: <span class="font-mono-code text-indigo-600 font-bold"><?= htmlspecialchars($room['room_code'] ?? '-') ?></span> | Durasi: <?= htmlspecialchars($room['duration'] ?? 0) ?> Menit
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

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="neon-border-wrapper">
                <div class="cyber-card bg-white border border-slate-200 p-5 shadow-lg flex items-center gap-4">
                    <div class="w-12 h-12 bg-indigo-500/10 border border-indigo-500/20 text-indigo-600 rounded-xl flex items-center justify-center text-xl">
                        <i class="fa-solid fa-users"></i>
                    </div>
                    <div>
                        <p class="text-xs text-slate-500 text-sub-title">Total Mahasiswa Joined</p>
                        <p class="text-2xl font-bold text-slate-900 text-main-title"><?= $total_students ?> <span class="text-xs font-normal text-slate-400">Orang</span></p>
                    </div>
                </div>
            </div>

            <div class="neon-border-wrapper">
                <div class="cyber-card bg-white border border-slate-200 p-5 shadow-lg flex items-center gap-4">
                    <div class="w-12 h-12 bg-emerald-500/10 border border-emerald-500/20 text-emerald-600 rounded-xl flex items-center justify-center text-xl">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                    </div>
                    <div>
                        <p class="text-xs text-slate-500 text-sub-title">Total Peringatan Siswa</p>
                        <p class="text-2xl font-bold text-slate-900 text-main-title"><?= $total_warnings ?> <span class="text-xs font-normal text-slate-400">Peringatan</span></p>
                    </div>
                </div>
            </div>

            <div class="neon-border-wrapper">
                <div class="cyber-card bg-white border border-slate-200 p-5 shadow-lg flex items-center gap-4">
                    <div class="w-12 h-12 bg-amber-500/10 border border-amber-500/20 text-amber-600 rounded-xl flex items-center justify-center text-xl">
                        <i class="fa-solid fa-key"></i>
                    </div>
                    <div>
                        <p class="text-xs text-slate-500 text-sub-title">Passcode Room</p>
                        <p class="text-xl font-mono-code font-bold text-amber-600"><?= htmlspecialchars($room['passcode'] ?? '-') ?></p>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 neon-border-wrapper">
                <div class="cyber-card bg-white border border-slate-200 p-6 shadow-xl space-y-4">
                    <div class="flex items-center justify-between border-b border-slate-200 pb-3" id="table-header-border">
                        <h2 class="text-xs font-bold text-indigo-600 uppercase tracking-wider flex items-center gap-2" id="section-1-title">
                            <i class="fa-solid fa-user-graduate"></i> Daftar Mahasiswa Terhubung
                        </h2>
                        <span class="text-[10px] text-slate-400 font-mono-code"><?= $total_students ?> Peserta</span>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-slate-100 text-slate-600 uppercase font-semibold text-[10px]" id="table-head">
                                <tr>
                                    <th class="p-3 rounded-l-lg">Nama Mahasiswa</th>
                                    <th class="p-3">NIM / Identitas</th>
                                    <th class="p-3">Waktu Masuk</th>
                                    <th class="p-3">Peringatan</th>
                                    <th class="p-3 rounded-r-lg text-right">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 text-slate-700" id="table-body">
                                <?php if (!empty($joined_students)): ?>
                                    <?php foreach ($joined_students as $student): ?>
                                        <tr class="hover:bg-slate-50 transition">
                                            <td class="p-3 font-semibold text-slate-900 text-main-title flex items-center gap-2">
                                                <div class="w-7 h-7 rounded-full bg-indigo-600/10 text-indigo-600 border border-indigo-500/20 flex items-center justify-center font-bold text-[10px]">
                                                    <?= strtoupper(substr($student['name'] ?? 'U', 0, 1)) ?>
                                                </div>
                                                <?= htmlspecialchars($student['name'] ?? '-') ?>
                                            </td>
                                            <td class="p-3 font-mono-code text-slate-500 text-sub-title"><?= htmlspecialchars($student['identity_number'] ?? '-') ?></td>
                                            <td class="p-3 text-slate-500 text-sub-title font-mono-code"><?= !empty($student['joined_at']) ? date('H:i:s', strtotime($student['joined_at'])) : '-' ?></td>
                                            <td class="p-3 font-mono-code text-indigo-600 font-semibold"><?= htmlspecialchars($student['warning_count'] ?? 0) ?> Peringatan</td>
                                            <td class="p-3 text-right">
                                                <div class="inline-flex items-center gap-2">
                                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-500/10 text-emerald-600 border border-emerald-500/20">
                                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> <?= htmlspecialchars(ucfirst($student['session_status'] ?? 'ongoing')) ?>
                                                    </span>
                                                    <a href="detailpengawasan.php?student_id=<?= urlencode($student['id'] ?? 0) ?>&room_id=<?= urlencode($actual_room_id) ?>" class="p-1.5 bg-indigo-600/10 hover:bg-indigo-600/20 text-indigo-600 rounded-lg transition" title="Detail Peringatan">
                                                        <i class="fa-regular fa-eye"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="p-6 text-center text-slate-400">Belum ada mahasiswa yang terhubung di room ujian ini.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="neon-border-wrapper">
                <div class="cyber-card bg-white border border-slate-200 p-6 shadow-xl space-y-4">
                    <div class="flex items-center justify-between border-b border-slate-200 pb-3" id="stream-header-border">
                        <h3 class="text-xs font-bold text-indigo-600 uppercase tracking-wider flex items-center gap-2" id="section-2-title">
                            <i class="fa-solid fa-triangle-exclamation"></i> Peringatan Stream
                        </h3>
                        <span class="text-[10px] text-slate-400 font-mono-code">Live Updates</span>
                    </div>

                    <div class="space-y-3 max-h-[420px] overflow-y-auto pr-1">
                        <?php if (!empty($recent_logs)): ?>
                            <?php foreach ($recent_logs as $log): ?>
                                <div class="bg-slate-50 border border-slate-200 p-3 rounded-xl text-xs space-y-1 stream-log-item">
                                    <div class="flex items-center justify-between">
                                        <span class="font-bold text-slate-800 text-[11px] text-main-title"><?= htmlspecialchars($log['student_name'] ?? '-') ?></span>
                                        <span class="text-[9px] font-mono-code text-slate-400"><?= !empty($log['submitted_at']) ? date('H:i:s', strtotime($log['submitted_at'])) : '-' ?></span>
                                    </div>
                                    <p class="text-[10px] text-slate-500 text-sub-title font-mono-code">NIM: <?= htmlspecialchars($log['identity_number'] ?? '-') ?></p>
                                    <div class="text-[10px] text-indigo-600 bg-indigo-50 px-2 py-1 rounded border border-indigo-100 mt-1 font-mono-code">
                                        <i class="fa-solid fa-triangle-exclamation text-[9px]"></i> Peringatan ke-<?= htmlspecialchars($log['id'] ?? '-') ?> diterima
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-center text-slate-400 text-xs py-10">Belum ada peringatan siswa yang tercatat.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        const mainBody = document.getElementById('main-body');
        const statusDot = document.getElementById('status-dot');
        const statusText = document.getElementById('status-text');
        const statusBadge = document.getElementById('server-status-badge');
        const headerBorder = document.getElementById('header-border');
        const tableHeaderBorder = document.getElementById('table-header-border');
        const streamHeaderBorder = document.getElementById('stream-header-border');
        const section1Title = document.getElementById('section-1-title');
        const section2Title = document.getElementById('section-2-title');
        const tableHead = document.getElementById('table-head');
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
                if (tableHeaderBorder) tableHeaderBorder.className = "flex items-center justify-between border-b border-slate-800 pb-3";
                if (streamHeaderBorder) streamHeaderBorder.className = "flex items-center justify-between border-b border-slate-800 pb-3";
                section1Title.className = "text-xs font-bold text-indigo-400 uppercase tracking-wider flex items-center gap-2";
                section2Title.className = "text-xs font-bold text-indigo-400 uppercase tracking-wider flex items-center gap-2";
                if (tableHead) tableHead.className = "bg-slate-950 text-slate-400 uppercase font-semibold text-[10px]";
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
                if (tableHeaderBorder) tableHeaderBorder.className = "flex items-center justify-between border-b border-slate-200 pb-3";
                if (streamHeaderBorder) streamHeaderBorder.className = "flex items-center justify-between border-b border-slate-200 pb-3";
                section1Title.className = "text-xs font-bold text-indigo-600 uppercase tracking-wider flex items-center gap-2";
                section2Title.className = "text-xs font-bold text-indigo-600 uppercase tracking-wider flex items-center gap-2";
                if (tableHead) tableHead.className = "bg-slate-100 text-slate-600 uppercase font-semibold text-[10px]";
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
