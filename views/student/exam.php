<?php
// views/student/exam.php
session_start();
require_once __DIR__ . '/../../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../auth/login.php");
    exit();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$room_id    = (int) ($_GET['room_id'] ?? 0);
$student_id = $_SESSION['user_id'];

if (!$room_id) {
    header("Location: dashboard.php");
    exit();
}

try {
    $stmtRoom = $pdo->prepare("SELECT * FROM rooms WHERE id = :id LIMIT 1");
    $stmtRoom->execute(['id' => $room_id]);
    $room = $stmtRoom->fetch(PDO::FETCH_ASSOC);

    $stmtSession = $pdo->prepare("
        SELECT * FROM sessions
        WHERE room_id = :room_id AND student_id = :student_id
        ORDER BY id DESC LIMIT 1
    ");
    $stmtSession->execute(['room_id' => $room_id, 'student_id' => $student_id]);
    $session = $stmtSession->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('[EXAM PAGE ERROR] ' . $e->getMessage());
    header("Location: dashboard.php");
    exit();
}

// Wajib sudah 'join' dulu lewat dashboard, dan sesi harus masih 'ongoing'
if (!$room || !$session) {
    $_SESSION['join_error'] = "Anda belum bergabung ke room ini. Masukkan ID Room dari dashboard.";
    header("Location: dashboard.php");
    exit();
}

if ($session['status'] === 'forfeited') {
    $_SESSION['join_error'] = "Ujian ini sudah gugur karena Anda pernah keluar dari halaman ujian.";
    header("Location: dashboard.php");
    exit();
}

if ($session['status'] === 'completed') {
    $_SESSION['join_error'] = "Anda sudah menyelesaikan ujian ini.";
    header("Location: dashboard.php");
    exit();
}

$max_violations = 3;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($room['subject_name']) ?> - ExamPro</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; background-color: #0b1120; }
        .card { background-color: #111a2e; border: 1px solid #1e293b; }
        /* Blokir seleksi teks & cegah klik-kanan sebagai lapisan anti-cheat tambahan (best effort) */
        body.exam-active { user-select: none; -webkit-user-select: none; }
    </style>
</head>
<body class="min-h-screen text-slate-200">

    <!-- OVERLAY: Wajib klik "Mulai Ujian" dulu supaya browser mengizinkan Fullscreen API -->
    <div id="start-overlay" class="fixed inset-0 z-50 bg-[#0b1120] flex items-center justify-center px-6">
        <div class="card rounded-2xl p-8 max-w-md w-full text-center">
            <i class="fa-solid fa-shield-halved text-indigo-400 text-3xl mb-4"></i>
            <h1 class="text-xl font-bold text-white mb-2"><?= htmlspecialchars($room['subject_name']) ?></h1>
            <p class="text-sm text-slate-400 mb-6">
                Ujian akan berjalan dalam mode layar penuh (fullscreen). Berpindah tab, keluar fullscreen,
                atau menutup halaman ini akan dicatat sebagai pelanggaran. Setelah
                <span class="text-red-400 font-semibold"><?= $max_violations ?> kali</span> pelanggaran,
                atau jika Anda menutup halaman ini, ujian otomatis dinyatakan <span class="text-red-400 font-semibold">gugur</span>
                dan tidak bisa dibuka lagi.
            </p>
            <button id="start-btn" class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold text-sm px-6 py-3 rounded-xl w-full transition">
                <i class="fa-solid fa-expand mr-2"></i> Mulai Ujian (Fullscreen)
            </button>
        </div>
    </div>

    <!-- WARNING BANNER: muncul saat pelanggaran terdeteksi -->
    <div id="violation-banner" class="hidden fixed top-0 left-0 right-0 z-40 bg-red-600 text-white text-sm font-semibold text-center py-2.5 px-4">
        <i class="fa-solid fa-triangle-exclamation mr-1"></i>
        <span id="violation-text">Pelanggaran terdeteksi.</span>
    </div>

    <!-- KONTEN UJIAN -->
    <main class="max-w-4xl mx-auto px-5 md:px-8 py-10">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-lg font-bold text-white"><?= htmlspecialchars($room['subject_name']) ?></h1>
                <p class="text-xs text-slate-500"><?= htmlspecialchars($room['class_name'] ?? '') ?> &bull; <?= htmlspecialchars($room['exam_type'] ?? 'Ujian') ?></p>
            </div>
            <div class="text-right">
                <p class="text-[11px] text-slate-500 uppercase tracking-wide">Pelanggaran</p>
                <p class="text-lg font-bold"><span id="violation-count" class="text-white">0</span><span class="text-slate-500">/<?= $max_violations ?></span></p>
            </div>
        </div>

        <div class="card rounded-2xl p-6 md:p-8">
            <p class="text-sm text-slate-400 leading-relaxed">
                <i class="fa-solid fa-circle-info text-indigo-400 mr-1"></i>
                Halaman ini adalah kerangka ruang ujian dengan proteksi anti-keluar-tab. Soal ujian belum
                diimplementasikan di sini — tinggal ganti bagian ini dengan komponen soal Anda
                (pilihan ganda, essay, kode, dsb).
            </p>
        </div>

        <form id="finish-form" action="../../controllers/student/finish_exam.php" method="POST" class="mt-6 text-right">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="room_id" value="<?= $room_id ?>">
            <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-sm px-6 py-3 rounded-xl transition">
                Selesai &amp; Kumpulkan Ujian
            </button>
        </form>
    </main>

    <script>
        const ROOM_ID        = <?= json_encode($room_id) ?>;
        const CSRF_TOKEN     = <?= json_encode($_SESSION['csrf_token']) ?>;
        const MAX_VIOLATIONS = <?= json_encode($max_violations) ?>;
        const VIOLATION_URL  = '../../controllers/student/report_violation.php';
        const LEAVE_URL      = '../../controllers/student/leave_exam.php';

        let violationCount = 0;
        let examStarted    = false;
        let isFinishing     = false; // supaya submit form resmi tidak ikut ke-flag sebagai "keluar"

        const overlay   = document.getElementById('start-overlay');
        const banner    = document.getElementById('violation-banner');
        const bannerText = document.getElementById('violation-text');
        const countEl   = document.getElementById('violation-count');

        function showBanner(msg) {
            bannerText.textContent = msg;
            banner.classList.remove('hidden');
            setTimeout(() => banner.classList.add('hidden'), 4000);
        }

        async function reportViolation(reason) {
            if (!examStarted || isFinishing) return;
            try {
                const res = await fetch(VIOLATION_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ room_id: ROOM_ID, csrf_token: CSRF_TOKEN, reason })
                });
                const data = await res.json();
                if (data.count !== undefined) {
                    violationCount = data.count;
                    countEl.textContent = violationCount;
                }
                if (data.forfeited) {
                    isFinishing = true;
                    alert('Ujian dinyatakan gugur karena terlalu banyak pelanggaran.');
                    window.location.href = 'dashboard.php';
                } else {
                    showBanner(`Pelanggaran terdeteksi (${reason}). Percobaan ke-${violationCount} dari ${MAX_VIOLATIONS}.`);
                }
            } catch (e) {
                console.error('Gagal melapor pelanggaran', e);
            }
        }

        // 1. Mulai ujian: wajib gesture klik user agar Fullscreen API diizinkan browser
        document.getElementById('start-btn').addEventListener('click', async () => {
            try {
                await document.documentElement.requestFullscreen();
            } catch (e) {
                console.warn('Fullscreen ditolak/tidak didukung, ujian tetap lanjut tanpa fullscreen.', e);
            }
            overlay.classList.add('hidden');
            document.body.classList.add('exam-active');
            examStarted = true;
        });

        // 2. Deteksi pindah tab / minimize / app switch
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                reportViolation('pindah tab / minimize');
            }
        });

        // 3. Deteksi keluar dari mode fullscreen
        document.addEventListener('fullscreenchange', () => {
            if (examStarted && !isFinishing && !document.fullscreenElement) {
                reportViolation('keluar fullscreen');
            }
        });

        // 4. Cegah klik kanan & beberapa shortcut devtools (best effort, bukan jaminan mutlak)
        document.addEventListener('contextmenu', (e) => { if (examStarted) e.preventDefault(); });
        document.addEventListener('keydown', (e) => {
            if (!examStarted) return;
            const blocked = (e.key === 'F12') ||
                             (e.ctrlKey && e.shiftKey && ['I', 'J', 'C'].includes(e.key)) ||
                             (e.ctrlKey && e.key === 'u');
            if (blocked) e.preventDefault();
        });

        // 5. Tutup tab / navigasi keluar / kembali (back) -> peringatan + gugurkan sesi via beacon
        window.addEventListener('beforeunload', (e) => {
            if (!examStarted || isFinishing) return;
            const payload = new URLSearchParams({ room_id: ROOM_ID, csrf_token: CSRF_TOKEN }).toString();
            navigator.sendBeacon(LEAVE_URL, new Blob([payload], { type: 'application/x-www-form-urlencoded' }));
            e.preventDefault();
            e.returnValue = 'Meninggalkan halaman ini akan menggugurkan ujian Anda. Yakin?';
            return e.returnValue;
        });

        // 6. Submit resmi "Selesai & Kumpulkan" tidak boleh ikut ke-treat sebagai pelanggaran
        document.getElementById('finish-form').addEventListener('submit', () => {
            isFinishing = true;
        });
    </script>
</body>
</html>