<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'lecturer') {
    header("Location: ../auth/login.php");
    exit();
}

require_once __DIR__ . '/../../config/db.php';

$siswa_list = [];
$error_message = '';

try {
    $stmt = $pdo->prepare("
        SELECT 
            id,
            name AS nama_pengguna, 
            identity_number AS nim, 
            created_at AS tanggal_dibuat 
        FROM users 
        WHERE role = 'student' 
        ORDER BY created_at DESC
    ");
    $stmt->execute();
    $siswa_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error_message = "Gagal mengambil data murid: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Murid - CodeProcess</title>
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
                    <h2 class="text-2xl font-bold text-slate-900 tracking-tight text-main-title transition-colors">Data Murid / Siswa</h2>
                    <div class="flex items-center gap-2 px-3 py-1 rounded-full bg-slate-200 border border-slate-300 text-[10px] font-mono-code transition-all duration-500" id="server-status-badge">
                        <span class="w-2 h-2 rounded-full bg-slate-400 transition-all duration-500" id="status-dot"></span>
                        <span class="text-slate-600 font-semibold tracking-wider" id="status-text">Status : Clear Mode</span>
                    </div>
                </div>
                <p class="text-xs text-slate-500 text-sub-title transition-colors">Daftar murid yang terdaftar / sudah bergabung ke room.</p>
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
                                <th class="px-6 py-4">Nama Pengguna</th>
                                <th class="px-6 py-4">Tanggal Dibuat</th>
                                <th class="px-6 py-4">NIM / Identitas</th>
                                <th class="px-6 py-4 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200/80 transition-colors">
                            <?php if (!empty($siswa_list)): ?>
                                <?php foreach ($siswa_list as $siswa): ?>
                                    <tr class="cyber-tr hover:bg-slate-50/80 transition-colors">
                                        <td class="px-6 py-4">
                                            <p class="font-bold text-main-title transition-colors"><?= htmlspecialchars($siswa['nama_pengguna'] ?? '-') ?></p>
                                        </td>
                                        <td class="px-6 py-4 text-sub-title text-xs transition-colors">
                                            <?= !empty($siswa['tanggal_dibuat']) ? date('d M Y, H:i', strtotime($siswa['tanggal_dibuat'])) : '-' ?>
                                        </td>
                                        <td class="px-6 py-4 font-mono-code text-xs text-sub-title transition-colors">
                                            <?= htmlspecialchars($siswa['nim'] ?? '-') ?>
                                        </td>
                                        <td class="px-6 py-4 text-center">
                                            <a href="view_detail.php?id=<?= urlencode($siswa['id']) ?>" class="text-xs font-semibold bg-indigo-600 hover:bg-indigo-500 text-white px-3 py-2 rounded-xl transition-all shadow-md inline-flex items-center gap-1.5">
                                                <i class="fa-solid fa-magnifying-glass"></i> Lihat Detail
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="px-6 py-12 text-center text-slate-400 text-xs">
                                        <i class="fa-solid fa-user-graduate text-3xl mb-2 block opacity-50"></i>
                                        Belum ada data murid yang terdaftar.
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
        function togglePower() {
            const body = document.getElementById('main-body');
            const speechText = document.getElementById('speech-text');
            const statusText = document.getElementById('status-text');
            const statusDot = document.getElementById('status-dot');
            const badge = document.getElementById('server-status-badge');

            const isOn = body.classList.toggle('power-on');

            if (isOn) {
                speechText.textContent = 'Klik untuk pindah ke Light Mode';
                statusText.textContent = 'Status : Dark Mode';
                statusText.classList.remove('text-slate-600');
                statusText.classList.add('text-emerald-400');
                statusDot.classList.remove('bg-slate-400');
                statusDot.classList.add('bg-emerald-400');
                badge.classList.remove('bg-slate-200', 'border-slate-300');
                badge.classList.add('bg-slate-900', 'border-emerald-500/40');
            } else {
                speechText.textContent = 'Klik untuk pindah ke Dark Mode';
                statusText.textContent = 'Status : Clear Mode';
                statusText.classList.remove('text-emerald-400');
                statusText.classList.add('text-slate-600');
                statusDot.classList.remove('bg-emerald-400');
                statusDot.classList.add('bg-slate-400');
                badge.classList.remove('bg-slate-900', 'border-emerald-500/40');
                badge.classList.add('bg-slate-200', 'border-slate-300');
            }
        }
    </script>
</body>
</html>