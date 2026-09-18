<?php
// views/student/dashboard.php
session_start();
require_once __DIR__ . '/../../config/db.php';

// Proteksi Halaman: Wajib login & role 'student'
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../auth/login.php");
    exit();
}

// Auto-logout sederhana jika tidak aktif > 30 menit
$timeout = 30 * 60;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout) {
    session_unset();
    session_destroy();
    header("Location: ../auth/login.php?status=session_expired");
    exit();
}
$_SESSION['last_activity'] = time();

// Token CSRF untuk form "Masuk Room"
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$student_id = $_SESSION['user_id'];

// Ambil ringkasan statistik ujian siswa
$total_ujian = 0;
$rata_rata   = null;
$riwayat     = [];
$join_error  = $_SESSION['join_error'] ?? null;
unset($_SESSION['join_error']);

try {
    $stmtTotal = $pdo->prepare("SELECT COUNT(*) AS total FROM sessions WHERE student_id = :sid AND status = 'completed'");
    $stmtTotal->execute(['sid' => $student_id]);
    $total_ujian = (int) ($stmtTotal->fetch()['total'] ?? 0);

    $stmtAvg = $pdo->prepare("SELECT AVG(score) AS rata FROM sessions WHERE student_id = :sid AND status = 'completed' AND score IS NOT NULL");
    $stmtAvg->execute(['sid' => $student_id]);
    $avgResult = $stmtAvg->fetch()['rata'] ?? null;
    $rata_rata = $avgResult !== null ? round((float) $avgResult, 1) : null;

    $stmtRiwayat = $pdo->prepare("
        SELECT r.subject_name, r.exam_type, s.score, s.finished_at
        FROM sessions s
        JOIN rooms r ON r.id = s.room_id
        WHERE s.student_id = :sid AND s.status = 'completed'
        ORDER BY s.finished_at DESC
        LIMIT 4
    ");
    $stmtRiwayat->execute(['sid' => $student_id]);
    $riwayat = $stmtRiwayat->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('[STUDENT DASHBOARD ERROR] ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Siswa - ExamPro</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; background-color: #0b1120; }
        .card { background-color: #111a2e; border: 1px solid #1e293b; }
        .status-ok {
            background-color: rgba(16, 185, 129, 0.1);
            color: #34d399;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }
        .status-warn {
            background-color: rgba(245, 158, 11, 0.1);
            color: #fbbf24;
            border: 1px solid rgba(245, 158, 11, 0.3);
        }
        .status-error {
            background-color: rgba(239, 68, 68, 0.1);
            color: #f87171;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }
    </style>
</head>
<body class="min-h-screen text-slate-200">

    <?php include __DIR__ . '/partials/navbar.php'; ?>

    <main class="max-w-6xl mx-auto px-5 md:px-8 py-8 grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- KOLOM KIRI -->
        <div class="lg:col-span-2 space-y-6">

            <!-- CARD: JOIN ROOM -->
            <div class="card rounded-2xl p-6 md:p-7 bg-gradient-to-br from-indigo-950/60 to-[#111a2e]">
                <h1 class="text-xl md:text-2xl font-bold text-white mb-1">Siap Ujian Hari Ini?</h1>
                <p class="text-sm text-slate-400 mb-5">Pastikan Anda telah menerima Kode Room dari pengawas ujian Anda.</p>

                <?php if ($join_error): ?>
                    <p class="text-xs text-red-400 bg-red-500/10 border border-red-500/30 px-3 py-2 rounded-lg mb-4">
                        <?= htmlspecialchars($join_error) ?>
                    </p>
                <?php endif; ?>

                <a href="../auth/room.php?csrf_token=<?= urlencode($_SESSION['csrf_token']) ?>"
                   class="flex items-center justify-center gap-2 bg-indigo-600 hover:bg-indigo-700 active:scale-95 text-white font-semibold text-sm px-6 py-3.5 rounded-xl transition w-full sm:w-auto">
                    <i class="fa-solid fa-key"></i>
                    Masuk
                </a>
            </div>

            <!-- CARD: RIWAYAT UJIAN -->
            <div class="card rounded-2xl p-6 md:p-7">
                <h2 class="text-base font-semibold text-white mb-4 flex items-center gap-2">
                    <i class="fa-solid fa-clock-rotate-left text-indigo-400"></i>
                    Riwayat Ujian Terakhir
                </h2>

                <?php if (empty($riwayat)): ?>
                    <div class="text-center py-10 text-slate-500 text-sm">
                        <i class="fa-regular fa-folder-open text-2xl mb-2 block"></i>
                        Belum ada riwayat ujian yang selesai dikerjakan.
                    </div>
                <?php else: ?>
                    <div class="divide-y divide-slate-800">
                        <?php foreach ($riwayat as $item): ?>
                            <div class="flex items-center justify-between py-3.5">
                                <div>
                                    <p class="text-sm font-semibold text-white">
                                        <?= htmlspecialchars($item['subject_name']) ?>
                                    </p>
                                    <p class="text-xs text-slate-500 mt-0.5">
                                        <?= htmlspecialchars(date('d M Y', strtotime($item['finished_at']))) ?>
                                        &bull; <?= htmlspecialchars($item['exam_type'] ?? 'Ujian') ?>
                                    </p>
                                </div>
                                <span class="text-emerald-400 font-bold text-base">
                                    <?= $item['score'] !== null ? htmlspecialchars(number_format((float) $item['score'], 0)) : '-' ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- KOLOM KANAN -->
        <div class="space-y-6">

            <!-- CARD: STATUS SISTEM -->
            <div class="card rounded-2xl p-6">
                <h2 class="text-base font-semibold text-white mb-1.5 flex items-center gap-2">
                    <i class="fa-solid fa-shield-halved text-indigo-400"></i>
                    Status Sistem
                </h2>
                <p class="text-xs text-slate-500 mb-5 leading-relaxed">
                    Sistem akan memverifikasi perangkat keras sebelum Anda diizinkan masuk ke ruang ujian.
                </p>

                <div class="space-y-2.5" id="system-status-list">
                    <div class="flex items-center justify-between bg-[#0b1120] border border-slate-800 rounded-xl px-4 py-3">
                        <span class="text-sm text-slate-300 flex items-center gap-2">
                            <i class="fa-solid fa-wifi text-slate-400 w-4"></i> Jaringan
                        </span>
                        <span id="badge-network" class="status-warn text-[11px] font-semibold px-2.5 py-1 rounded-full">Memeriksa</span>
                    </div>
                    <div class="flex items-center justify-between bg-[#0b1120] border border-slate-800 rounded-xl px-4 py-3">
                        <span class="text-sm text-slate-300 flex items-center gap-2">
                            <i class="fa-solid fa-video text-slate-400 w-4"></i> Webcam
                        </span>
                        <span id="badge-webcam" class="status-warn text-[11px] font-semibold px-2.5 py-1 rounded-full">Memeriksa</span>
                    </div>
                    <div class="flex items-center justify-between bg-[#0b1120] border border-slate-800 rounded-xl px-4 py-3">
                        <span class="text-sm text-slate-300 flex items-center gap-2">
                            <i class="fa-solid fa-microphone text-slate-400 w-4"></i> Audio (Mic)
                        </span>
                        <span id="badge-mic" class="status-warn text-[11px] font-semibold px-2.5 py-1 rounded-full">Memeriksa</span>
                    </div>
                    <div class="flex items-center justify-between bg-[#0b1120] border border-slate-800 rounded-xl px-4 py-3">
                        <span class="text-sm text-slate-300 flex items-center gap-2">
                            <i class="fa-solid fa-globe text-slate-400 w-4"></i> Browser
                        </span>
                        <span id="badge-browser" class="status-ok text-[11px] font-semibold px-2.5 py-1 rounded-full">Didukung</span>
                    </div>
                </div>
            </div>

            <!-- STAT BOXES -->
            <div class="grid grid-cols-2 gap-4">
                <div class="card rounded-2xl p-5 text-center">
                    <p class="text-2xl md:text-3xl font-bold text-white"><?= $total_ujian ?></p>
                    <p class="text-[11px] text-slate-500 mt-1 uppercase tracking-wide">Total Ujian</p>
                </div>
                <div class="card rounded-2xl p-5 text-center">
                    <p class="text-2xl md:text-3xl font-bold text-white"><?= $rata_rata !== null ? htmlspecialchars($rata_rata) : '-' ?></p>
                    <p class="text-[11px] text-slate-500 mt-1 uppercase tracking-wide">Rata-rata</p>
                </div>
            </div>
        </div>
    </main>

    <!-- SCRIPT: CEK PERANGKAT REAL-TIME -->
    <script>
        function setBadge(id, ok, okLabel, badLabel) {
            const el = document.getElementById(id);
            if (ok) {
                el.textContent = okLabel;
                el.className = 'status-ok text-[11px] font-semibold px-2.5 py-1 rounded-full';
            } else {
                el.textContent = badLabel;
                el.className = 'status-error text-[11px] font-semibold px-2.5 py-1 rounded-full';
            }
        }

        // 1. Cek Jaringan
        setBadge('badge-network', navigator.onLine, 'Stabil', 'Terputus');
        window.addEventListener('online', () => setBadge('badge-network', true, 'Stabil', 'Terputus'));
        window.addEventListener('offline', () => setBadge('badge-network', false, 'Stabil', 'Terputus'));

        // 2. Cek Webcam & Mic (butuh izin browser; halaman harus diakses via HTTPS/localhost)
        async function checkMediaDevices() {
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                setBadge('badge-webcam', false, 'Terdeteksi', 'Tidak Didukung');
                setBadge('badge-mic', false, 'Terdeteksi', 'Tidak Didukung');
                return;
            }
            try {
                const stream = await navigator.mediaDevices.getUserMedia({ video: true, audio: true });
                setBadge('badge-webcam', true, 'Terdeteksi', 'Tidak Ada');
                setBadge('badge-mic', true, 'Terdeteksi', 'Tidak Ada');
                // Hentikan stream, ini hanya pengecekan izin awal
                stream.getTracks().forEach(track => track.stop());
            } catch (err) {
                setBadge('badge-webcam', false, 'Terdeteksi', 'Izin Ditolak');
                setBadge('badge-mic', false, 'Terdeteksi', 'Izin Ditolak');
            }
        }
        checkMediaDevices();

        // 3. Cek Browser (dukungan fitur dasar yang dibutuhkan sistem ujian)
        const browserSupported = !!(window.WebSocket && navigator.mediaDevices);
        setBadge('badge-browser', browserSupported, 'Didukung', 'Tidak Didukung');
    </script>
</body>
</html>