<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();

// 1. Koneksi Database
require_once __DIR__ . '/../../config/db.php';

// 2. Proteksi Halaman: Wajib login & role 'lecturer'
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'lecturer') {
    header("Location: ../auth/login.php");
    exit();
}

$lecturer_id = $_SESSION['user_id'];

// 3. Ambil ID Room dari query string (?id=...)
$room_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($room_id <= 0) {
    $_SESSION['error'] = "Room tidak ditemukan.";
    header("Location: dashboard.php");
    exit();
}

// 4. Pemproses Form Submit (Backend Logic - UPDATE)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $exam_name   = trim($_POST['exam_name'] ?? '');
    $class       = trim($_POST['class'] ?? '');
    $duration    = (int)($_POST['duration'] ?? 0);
    $start_time  = $_POST['start_time'] ?? null;
    $status      = ($_POST['status'] ?? 'active') === 'active' ? 'active' : 'inactive';
    $post_id     = (int)($_POST['room_id'] ?? 0);

    if (empty($exam_name) || empty($duration)) {
        $_SESSION['error'] = "Harap isi semua kolom yang wajib (*)!";
        header("Location: edit_room.php?id=" . $post_id);
        exit();
    }

    try {
        $stmt = $pdo->prepare("
            UPDATE rooms
            SET subject_name = :subject_name,
                class_name   = :class_name,
                duration     = :duration,
                start_time   = :start_time,
                status       = :status
            WHERE id = :room_id AND lecturer_id = :lecturer_id
        ");

        $stmt->execute([
            'subject_name' => $exam_name,
            'class_name'   => $class,
            'duration'     => $duration,
            'start_time'   => !empty($start_time) ? $start_time : null,
            'status'       => $status,
            'room_id'      => $post_id,
            'lecturer_id'  => $lecturer_id
        ]);

        if ($stmt->rowCount() === 0) {
            $_SESSION['error'] = "Room tidak ditemukan atau bukan milik Anda.";
            header("Location: dashboard.php");
            exit();
        }

        $_SESSION['success'] = "Room ujian '$exam_name' berhasil diperbarui!";
        header("Location: dashboard.php");
        exit();

    } catch (PDOException $e) {
        $_SESSION['error'] = "Gagal memperbarui room: " . $e->getMessage();
        header("Location: edit_room.php?id=" . $post_id);
        exit();
    }
}

// 5. Ambil Data Room untuk Ditampilkan (Frontend)
try {
    $stmt = $pdo->prepare("SELECT * FROM rooms WHERE id = :room_id AND lecturer_id = :lecturer_id LIMIT 1");
    $stmt->execute([
        'room_id'     => $room_id,
        'lecturer_id' => $lecturer_id
    ]);
    $room = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$room) {
        $_SESSION['error'] = "Room tidak ditemukan atau bukan milik Anda.";
        header("Location: dashboard.php");
        exit();
    }
} catch (PDOException $e) {
    $_SESSION['error'] = "Gagal mengambil data room: " . $e->getMessage();
    header("Location: dashboard.php");
    exit();
}

$error_message = $_SESSION['error'] ?? '';
unset($_SESSION['error']);

// Value bantu supaya datetime-local terisi dengan format yang tepat (YYYY-MM-DDTHH:MM)
$start_time_value = '';
if (!empty($room['start_time'])) {
    $start_time_value = date('Y-m-d\TH:i', strtotime($room['start_time']));
}
$is_active = ($room['status'] === 'active');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Room Ujian - CodeProcess</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Google Fonts -->
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

        .cyber-input { transition: all 0.3s ease; }

        .power-on .cyber-input {
            background-color: #0b0f19 !important;
            border-color: #334155 !important;
            color: #f8fafc !important;
        }

        .power-on .cyber-input:focus {
            border-color: #10b981 !important;
            box-shadow: 0 0 10px rgba(16, 185, 129, 0.3);
        }

        .power-on .cyber-input::placeholder { color: #64748b !important; }
        .power-on .text-main-title { color: #ffffff !important; }
        .power-on .text-sub-title { color: #94a3b8 !important; }

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

        /* Toggle Switch Active/Inactive */
        .toggle-checkbox:checked {
            right: 0;
            border-color: #10b981;
        }
        .toggle-checkbox:checked + .toggle-label {
            background-color: #10b981;
        }
        .toggle-checkbox {
            right: 24px;
            transition: all 0.3s ease;
        }
        .toggle-label {
            transition: background-color 0.3s ease;
        }
    </style>
</head>
<body class="bg-slate-100 text-slate-800 min-h-screen flex" id="main-body">

    <?php
    $sidebar_path = __DIR__ . '/sidebar.php';
    if (file_exists($sidebar_path)) {
        include $sidebar_path;
    }
    ?>

    <main class="flex-1 p-8 max-w-5xl mx-auto space-y-6 relative">

        <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4 border-b border-slate-200 pb-5 transition-colors" id="header-border">
            <div>
                <div class="flex items-center gap-3 mb-1">
                    <h1 class="text-2xl font-bold text-slate-900 tracking-tight text-main-title transition-colors">Edit Room Ujian</h1>
                    <div class="flex items-center gap-2 px-3 py-1 rounded-full bg-slate-200 border border-slate-300 text-[10px] font-mono-code transition-all duration-500" id="server-status-badge">
                        <span class="w-2 h-2 rounded-full bg-slate-400 transition-all duration-500" id="status-dot"></span>
                        <span class="text-slate-600 font-semibold tracking-wider" id="status-text">Status : Clear Mode</span>
                    </div>
                </div>
                <p class="text-xs text-slate-500 text-sub-title transition-colors">Perbarui nama ujian, kelas, durasi/timer, dan status aktif room.</p>
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
            <form id="editRoomForm" action="edit_room.php?id=<?= (int)$room['id'] ?>" method="POST" class="cyber-card bg-white border border-slate-200 p-6 md:p-8 space-y-6 shadow-xl">

                <input type="hidden" name="room_id" value="<?= (int)$room['id'] ?>">

                <div class="space-y-4">
                    <h2 class="text-xs font-bold text-indigo-600 uppercase tracking-wider flex items-center gap-2" id="section-1-title">
                        <i class="fa-solid fa-sliders"></i> Parameter Ruang Ujian
                    </h2>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div class="md:col-span-2">
                            <label class="block text-xs font-semibold text-slate-700 text-sub-title mb-1.5">
                                Nama Ujian / Mata Kuliah <span class="text-rose-400">*</span>
                            </label>
                            <input type="text" name="exam_name" required value="<?= htmlspecialchars($room['subject_name']) ?>" placeholder="Contoh: Ujian Tengah Semester Pemrograman Web" class="cyber-input w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-3 text-xs text-slate-900 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none placeholder:text-slate-400">
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 text-sub-title mb-1.5">Kelas / Jurusan</label>
                            <input type="text" name="class" value="<?= htmlspecialchars($room['class_name'] ?? '') ?>" placeholder="Contoh: IF-A 2024" class="cyber-input w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-3 text-xs text-slate-900 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none placeholder:text-slate-400">
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 text-sub-title mb-1.5">
                                Durasi Ujian / Timer (Menit) <span class="text-rose-400">*</span>
                            </label>
                            <input type="number" name="duration" min="5" max="300" required value="<?= (int)$room['duration'] ?>" placeholder="90" class="cyber-input w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-3 text-xs text-slate-900 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none font-mono">
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-xs font-semibold text-slate-700 text-sub-title mb-1.5">Waktu Mulai Ujian</label>
                            <input type="datetime-local" name="start_time" value="<?= htmlspecialchars($start_time_value) ?>" class="cyber-input w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-3 text-xs text-slate-800 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none">
                        </div>
                    </div>
                </div>

                <hr class="border-slate-200 transition-colors" id="form-divider">

                <div class="pt-2 space-y-5">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div class="p-5 rounded-xl border border-indigo-200 bg-indigo-50/50 transition-all" id="room-code-card">
                            <span class="text-[11px] font-bold text-indigo-600 uppercase tracking-wide" id="room-code-label">Room Code</span>
                            <p class="text-2xl font-mono-code font-extrabold text-indigo-700 tracking-wider mt-0.5"><?= htmlspecialchars($room['room_code']) ?></p>
                        </div>

                        <div class="p-5 rounded-xl border border-slate-200 bg-slate-50 flex items-center justify-between gap-4 transition-all" id="status-toggle-card">
                            <div>
                                <span class="text-[11px] font-bold text-slate-600 uppercase tracking-wide" id="status-toggle-label">Status Room</span>
                                <p class="text-sm font-semibold text-slate-800 mt-0.5" id="status-toggle-text"><?= $is_active ? 'Aktif' : 'Nonaktif' ?></p>
                            </div>

                            <div class="relative inline-block w-12 align-middle select-none">
                                <input type="checkbox" name="status_toggle" id="statusToggle" <?= $is_active ? 'checked' : '' ?> class="toggle-checkbox absolute block w-6 h-6 rounded-full bg-white border-4 border-slate-300 appearance-none cursor-pointer">
                                <label for="statusToggle" class="toggle-label block overflow-hidden h-6 rounded-full bg-slate-300 cursor-pointer"></label>
                            </div>
                            <input type="hidden" name="status" id="statusInput" value="<?= $is_active ? 'active' : 'inactive' ?>">
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-2">
                        <a href="dashboard.php" class="px-5 py-3 bg-slate-100 hover:bg-rose-500/10 text-slate-500 hover:text-rose-500 text-xs font-semibold rounded-xl border border-slate-300 hover:border-rose-500/30 transition flex items-center gap-2" id="btn-reset">
                            <i class="fa-solid fa-xmark"></i> Batal
                        </a>
                        <button type="submit" class="px-6 py-3 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-xl transition shadow-lg hover:shadow-indigo-500/30 flex items-center gap-2">
                            <i class="fa-solid fa-floppy-disk"></i> Simpan Perubahan
                        </button>
                    </div>
                </div>

            </form>
        </div>
    </main>

    <script>
        const mainBody = document.getElementById('main-body');
        const statusDot = document.getElementById('status-dot');
        const statusText = document.getElementById('status-text');
        const statusBadge = document.getElementById('server-status-badge');
        const headerBorder = document.getElementById('header-border');
        const formDivider = document.getElementById('form-divider');
        const section1Title = document.getElementById('section-1-title');
        const roomCodeCard = document.getElementById('room-code-card');
        const roomCodeLabel = document.getElementById('room-code-label');
        const btnReset = document.getElementById('btn-reset');
        const statusToggleCard = document.getElementById('status-toggle-card');
        const statusToggleLabel = document.getElementById('status-toggle-label');

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

        // Toggle status Aktif/Nonaktif
        const statusToggle = document.getElementById('statusToggle');
        const statusInput = document.getElementById('statusInput');
        const statusToggleText = document.getElementById('status-toggle-text');

        statusToggle.addEventListener('change', function () {
            if (this.checked) {
                statusInput.value = 'active';
                statusToggleText.textContent = 'Aktif';
            } else {
                statusInput.value = 'inactive';
                statusToggleText.textContent = 'Nonaktif';
            }
        });

        function togglePower() {
            isPowerOn = !isPowerOn;

            if (isPowerOn) {
                mainBody.classList.add('power-on');
                headerBorder.className = "flex flex-col md:flex-row items-start md:items-center justify-between gap-4 border-b border-slate-800/80 pb-5 transition-colors";
                formDivider.className = "border-slate-800 transition-colors";

                section1Title.className = "text-xs font-bold text-indigo-400 uppercase tracking-wider flex items-center gap-2";

                roomCodeCard.className = "p-5 rounded-xl border border-indigo-500/20 bg-slate-950/80 transition-all";
                roomCodeLabel.className = "text-[11px] font-bold text-indigo-400 uppercase tracking-wide";
                statusToggleCard.className = "p-5 rounded-xl border border-slate-800 bg-slate-950/80 flex items-center justify-between gap-4 transition-all";
                statusToggleLabel.className = "text-[11px] font-bold text-slate-400 uppercase tracking-wide";
                btnReset.className = "px-5 py-3 bg-slate-950 hover:bg-rose-500/10 text-slate-400 hover:text-rose-400 text-xs font-semibold rounded-xl border border-slate-800 hover:border-rose-500/30 transition flex items-center gap-2";

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
                formDivider.className = "border-slate-200 transition-colors";

                section1Title.className = "text-xs font-bold text-indigo-600 uppercase tracking-wider flex items-center gap-2";

                roomCodeCard.className = "p-5 rounded-xl border border-indigo-200 bg-indigo-50/50 transition-all";
                roomCodeLabel.className = "text-[11px] font-bold text-indigo-600 uppercase tracking-wide";
                statusToggleCard.className = "p-5 rounded-xl border border-slate-200 bg-slate-50 flex items-center justify-between gap-4 transition-all";
                statusToggleLabel.className = "text-[11px] font-bold text-slate-600 uppercase tracking-wide";
                btnReset.className = "px-5 py-3 bg-slate-100 hover:bg-rose-500/10 text-slate-500 hover:text-rose-500 text-xs font-semibold rounded-xl border border-slate-300 hover:border-rose-500/30 transition flex items-center gap-2";

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