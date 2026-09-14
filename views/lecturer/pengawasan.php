<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// Proteksi Akses Dosen
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'lecturer') {
    header("Location: ../auth/login.php");
    exit();
}

require_once __DIR__ . '/../../config/db.php';

$room_id = $_GET['id'] ?? $_SESSION['active_room_id'] ?? null;

if (!$room_id) {
    header("Location: dashboard.php");
    exit();
}

try {
    // 1. Ambil data room dari tabel 'rooms'
    $stmt_room = $pdo->prepare("SELECT * FROM rooms WHERE id = :id LIMIT 1");
    $stmt_room->execute(['id' => $room_id]);
    $room = $stmt_room->fetch(PDO::FETCH_ASSOC);

    if (!$room) {
        die("Room ujian tidak ditemukan!");
    }

    // 2. Ambil daftar mahasiswa yang terhubung (JOIN via tabel sessions)
    $stmt_students = $pdo->prepare("
        SELECT DISTINCT u.id, u.name, u.identity_number, MIN(t.submitted_at) as joined_at, COUNT(t.id) as log_count
        FROM telemetry_logs t
        JOIN sessions s ON t.session_id = s.id
        JOIN users u ON s.student_id = u.id
        WHERE s.room_id = :room_id
        GROUP BY u.id, u.name, u.identity_number
        ORDER BY joined_at DESC
    ");
    $stmt_students->execute(['room_id' => $room_id]);
    $joined_students = $stmt_students->fetchAll(PDO::FETCH_ASSOC);

    // 3. Hitung statistik
    $total_students = count($joined_students);

    $stmt_logs_count = $pdo->prepare("
        SELECT COUNT(t.id) 
        FROM telemetry_logs t
        JOIN sessions s ON t.session_id = s.id
        WHERE s.room_id = :room_id
    ");
    $stmt_logs_count->execute(['room_id' => $room_id]);
    $total_logs = $stmt_logs_count->fetchColumn();

    // 4. Ambil 10 log telemetri terbaru
    $stmt_logs = $pdo->prepare("
        SELECT t.*, u.name as student_name, u.identity_number 
        FROM telemetry_logs t
        JOIN sessions s ON t.session_id = s.id
        JOIN users u ON s.student_id = u.id
        WHERE s.room_id = :room_id
        ORDER BY t.submitted_at DESC LIMIT 10
    ");
    $stmt_logs->execute(['room_id' => $room_id]);
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
    <title>Pengawasan Ujian: <?= htmlspecialchars($room['subject_name']) ?> - CodeProcess</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Refresh otomatis tiap 15 detik untuk update data live -->
    <meta http-equiv="refresh" content="15">
</head>
<body class="bg-slate-950 text-slate-100 font-sans min-h-screen flex">

    <?php 
    if (file_exists('sidebar.php')) {
        include 'sidebar.php'; 
    }
    ?>

    <main class="flex-1 p-8 space-y-6">
        
        <!-- Header Room & Aksi -->
        <div class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-800 pb-5">
            <div>
                <div class="flex items-center gap-3">
                    <a href="dashboard.php" class="text-slate-400 hover:text-white transition text-sm">
                        <i class="fa-solid fa-arrow-left"></i> Kembali ke Dashboard
                    </a>
                    <span class="px-2.5 py-0.5 text-[10px] uppercase font-bold rounded-full bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 flex items-center gap-1.5">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span> Mode Pengawasan Active
                    </span>
                </div>
                <h1 class="text-2xl font-bold text-white mt-1"><?= htmlspecialchars($room['subject_name']) ?></h1>
                <p class="text-xs text-slate-400 mt-0.5">Kode Ujian: <span class="font-mono text-indigo-400 font-bold"><?= htmlspecialchars($room['room_code']) ?></span> | Durasi: <?= $room['duration'] ?> Menit</p>
            </div>

            <div class="flex items-center gap-3">
                <button onclick="navigator.clipboard.writeText('<?= $room['room_code'] ?>')" class="px-4 py-2.5 bg-slate-900 hover:bg-slate-800 text-slate-300 text-xs font-semibold rounded-xl border border-slate-800 transition flex items-center gap-2">
                    <i class="fa-regular fa-copy"></i> Salin Kode Room
                </button>
            </div>
        </div>

        <!-- Metric Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="bg-slate-900 border border-slate-800 p-5 rounded-2xl flex items-center gap-4">
                <div class="w-12 h-12 bg-indigo-500/10 border border-indigo-500/20 text-indigo-400 rounded-xl flex items-center justify-center text-xl">
                    <i class="fa-solid fa-users"></i>
                </div>
                <div>
                    <p class="text-xs text-slate-400">Total Mahasiswa Terhubung</p>
                    <p class="text-2xl font-bold text-white"><?= $total_students ?> <span class="text-xs font-normal text-slate-500">Orang</span></p>
                </div>
            </div>

            <div class="bg-slate-900 border border-slate-800 p-5 rounded-2xl flex items-center gap-4">
                <div class="w-12 h-12 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 rounded-xl flex items-center justify-center text-xl">
                    <i class="fa-solid fa-receipt"></i>
                </div>
                <div>
                    <p class="text-xs text-slate-400">Total Record Telemetri</p>
                    <p class="text-2xl font-bold text-white"><?= $total_logs ?> <span class="text-xs font-normal text-slate-500">Log</span></p>
                </div>
            </div>

            <div class="bg-slate-900 border border-slate-800 p-5 rounded-2xl flex items-center gap-4">
                <div class="w-12 h-12 bg-amber-500/10 border border-amber-500/20 text-amber-400 rounded-xl flex items-center justify-center text-xl">
                    <i class="fa-solid fa-key"></i>
                </div>
                <div>
                    <p class="text-xs text-slate-400">Passcode Room</p>
                    <p class="text-xl font-mono font-bold text-white"><?= htmlspecialchars($room['passcode']) ?></p>
                </div>
            </div>
        </div>

        <!-- Area Pengawasan Utama -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <!-- Daftar Peserta -->
            <div class="lg:col-span-2 bg-slate-900 border border-slate-800 rounded-2xl p-6 space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-base font-bold text-white flex items-center gap-2">
                        <i class="fa-solid fa-user-graduate text-indigo-400"></i> Daftar Mahasiswa di Room
                    </h2>
                    <span class="text-xs text-slate-400 font-mono"><?= $total_students ?> Peserta</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-950 text-slate-400 uppercase font-semibold text-[10px]">
                            <tr>
                                <th class="p-3 rounded-l-lg">Nama Mahasiswa</th>
                                <th class="p-3">NIM / Identitas</th>
                                <th class="p-3">Waktu Masuk</th>
                                <th class="p-3">Aktivitas Telemetri</th>
                                <th class="p-3 rounded-r-lg text-right">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/60 text-slate-300">
                            <?php if (!empty($joined_students)): ?>
                                <?php foreach ($joined_students as $student): ?>
                                    <tr class="hover:bg-slate-800/30 transition">
                                        <td class="p-3 font-semibold text-white flex items-center gap-2">
                                            <div class="w-7 h-7 rounded-full bg-indigo-600/20 text-indigo-400 border border-indigo-500/30 flex items-center justify-center font-bold text-[10px]">
                                                <?= strtoupper(substr($student['name'], 0, 1)) ?>
                                            </div>
                                            <?= htmlspecialchars($student['name']) ?>
                                        </td>
                                        <td class="p-3 font-mono text-slate-400"><?= htmlspecialchars($student['identity_number']) ?></td>
                                        <td class="p-3 text-slate-400 font-mono"><?= date('H:i:s', strtotime($student['joined_at'])) ?></td>
                                        <td class="p-3 font-mono text-indigo-400"><?= $student['log_count'] ?> Record</td>
                                        <td class="p-3 text-right">
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                                <span class="w-1 h-1 rounded-full bg-emerald-400"></span> Aktif
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="p-6 text-center text-slate-500">
                                        Belum ada mahasiswa yang terhubung di room ujian ini.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Feed Telemetri Realtime -->
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                    <h3 class="text-sm font-bold text-white flex items-center gap-2">
                        <i class="fa-solid fa-stream text-emerald-400"></i> Stream Telemetri
                    </h3>
                    <span class="text-[10px] text-slate-500 font-mono">Live Log</span>
                </div>

                <div class="space-y-3 max-h-[400px] overflow-y-auto pr-1">
                    <?php if (!empty($recent_logs)): ?>
                        <?php foreach ($recent_logs as $log): ?>
                            <div class="bg-slate-950 border border-slate-800/80 p-3 rounded-xl text-xs space-y-1">
                                <div class="flex items-center justify-between">
                                    <span class="font-bold text-slate-200 text-[11px]"><?= htmlspecialchars($log['student_name']) ?></span>
                                    <span class="text-[9px] font-mono text-slate-500"><?= date('H:i:s', strtotime($log['submitted_at'])) ?></span>
                                </div>
                                <p class="text-[10px] text-slate-400 font-mono">NIM: <?= htmlspecialchars($log['identity_number']) ?></p>
                                <div class="text-[10px] text-indigo-400 bg-indigo-500/5 px-2 py-1 rounded border border-indigo-500/10 mt-1">
                                    <i class="fa-solid fa-code text-[9px]"></i> Log ketikan diterima
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-center text-slate-500 text-xs py-8">Belum ada aktivitas telemetri.</p>
                    <?php endif; ?>
                </div>
            </div>

        </div>

    </main>

</body>
</html>