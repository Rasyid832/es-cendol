<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'lecturer') {
    header("Location: ../auth/login.php");
    exit();
}

// =========================================================
// DUMMY DATA — belum ada backend/tabel submission & rekaman.
// Nanti diganti query ke tabel submissions/logs/recordings
// berdasarkan $_GET['nim'] (atau session_id yang lebih tepat).
// =========================================================
$nim = $_GET['nim'] ?? 'G1A022001';

$siswa = [
    'nama_pengguna' => 'Ahmad Fauzan',
    'nim'           => $nim,
    'room'          => 'Pemrograman Web - UTS Ganjil 2026',
    'waktu_mulai'   => '2026-09-01 08:15:00',
    'waktu_selesai' => '2026-09-01 09:45:12',
];

$soal_list = [
    [
        'judul'   => 'Soal 1 — Kalkulator Sederhana',
        'bahasa'  => 'PHP',
        'status'  => 'Diterima',
        'skor'    => 100,
        'waktu_submit' => '2026-09-01 08:52:03',
        'kode'    => <<<'CODE'
<?php
function kalkulator($a, $b, $operator) {
    switch ($operator) {
        case '+': return $a + $b;
        case '-': return $a - $b;
        case '*': return $a * $b;
        case '/':
            if ($b == 0) {
                return "Error: pembagian dengan nol";
            }
            return $a / $b;
        default:
            return "Operator tidak dikenali";
    }
}

echo kalkulator(10, 5, '+'); // 15
CODE
    ],
];

$soal         = $soal_list[0];
$durasi_menit = round((strtotime($siswa['waktu_selesai']) - strtotime($siswa['waktu_mulai'])) / 60);

$error_message = '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Hasil Ujian - CodeProcess</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/atom-one-dark.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Fira+Code:wght@400;600&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; transition: background-color 0.5s ease, color 0.5s ease; }
        .font-mono-code, pre, code { font-family: 'Fira Code', monospace; }
        @keyframes rotateBorder { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        body.power-on { background-color: #0b0f19 !important; color: #f8fafc; }
        .neon-border-wrapper { position: relative; border-radius: 1.5rem; padding: 2px; overflow: hidden; transition: all 0.5s ease; }
        .power-on .neon-border-wrapper::before { content: ''; position: absolute; top: -50%; left: -50%; width: 200%; height: 200%; background: conic-gradient(transparent 0deg, transparent 280deg, #6366f1 310deg, #10b981 360deg); animation: rotateBorder 4s linear infinite; z-index: 0; }
        .cyber-card { position: relative; z-index: 1; border-radius: 1.4rem; transition: background-color 0.5s ease, border-color 0.5s ease, box-shadow 0.5s ease; }
        .power-on .cyber-card { background-color: #0f172a !important; border-color: transparent !important; box-shadow: 0 0 25px rgba(16, 185, 129, 0.15), 0 0 10px rgba(99, 102, 241, 0.2); }
        .power-on .text-main-title { color: #ffffff !important; }
        .power-on .text-sub-title { color: #94a3b8 !important; }
        .power-on .side-card { background-color: #0f172a !important; border-color: #1e293b !important; }
        .power-on .placeholder-box { background-color: #0b0f19 !important; border-color: #1e293b !important; color: #64748b !important; }
        @keyframes floatDrone { 0%, 100% { transform: translateY(0px) rotate(0deg); } 50% { transform: translateY(-8px) rotate(-2deg); } }
        @keyframes shadowScale { 0%, 100% { transform: scale(1); opacity: 0.3; } 50% { transform: scale(0.7); opacity: 0.15; } }
        .drone-floating { animation: floatDrone 3.5s ease-in-out infinite; }
        .drone-shadow { animation: shadowScale 3.5s ease-in-out infinite; }
        pre.hljs { border-radius: 0.9rem; padding: 1.25rem; font-size: 12.5px; line-height: 1.6; }
    </style>
</head>
<body class="bg-slate-100 text-slate-800 min-h-screen flex" id="main-body">

    <?php if (file_exists('sidebar.php')) include 'sidebar.php'; ?>

    <main class="flex-1 p-8 space-y-6 relative max-w-6xl mx-auto">

        <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4 border-b border-slate-200 pb-5 transition-colors">
            <div>
                <a href="list_siswa.php" class="text-xs text-indigo-500 font-semibold inline-flex items-center gap-1.5 mb-2 hover:underline">
                    <i class="fa-solid fa-arrow-left"></i> Kembali ke Daftar Murid
                </a>
                <div class="flex items-center gap-3 mb-1">
                    <h2 class="text-2xl font-bold text-slate-900 tracking-tight text-main-title transition-colors">Detail Hasil Ujian</h2>
                </div>
                <p class="text-xs text-slate-500 text-sub-title transition-colors"><?= htmlspecialchars($siswa['room']) ?></p>
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

        <!-- Info Murid -->
        <div class="cyber-card bg-white border border-slate-200 rounded-2xl shadow-xl p-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-2xl bg-indigo-600 text-white flex items-center justify-center font-bold text-lg shrink-0">
                    <?= strtoupper(substr($siswa['nama_pengguna'], 0, 1)) ?>
                </div>
                <div>
                    <p class="font-bold text-main-title transition-colors"><?= htmlspecialchars($siswa['nama_pengguna']) ?></p>
                    <p class="text-xs text-sub-title font-mono-code transition-colors">NIM: <?= htmlspecialchars($siswa['nim']) ?></p>
                </div>
            </div>
            <div class="text-xs text-sub-title transition-colors md:text-right">
                <p><?= date('d M Y, H:i', strtotime($siswa['waktu_mulai'])) ?> — <?= date('H:i', strtotime($siswa['waktu_selesai'])) ?></p>
                <p>Durasi pengerjaan: <?= $durasi_menit ?> menit</p>
            </div>
        </div>

        <!-- Konten utama: kode (kiri) + rekaman (kanan) -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- Hasil Akhir Coding -->
            <div class="lg:col-span-2 neon-border-wrapper">
                <div class="cyber-card bg-white border border-slate-200 rounded-2xl shadow-xl p-5">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="font-bold text-main-title transition-colors text-sm">Hasil Akhir Coding</h3>
                    </div>

                    <p class="text-xs font-semibold text-sub-title transition-colors mb-3"><?= htmlspecialchars($soal['judul']) ?></p>

                    <div class="flex flex-wrap items-center gap-2 mb-3 text-xs">
                        <span class="font-mono-code bg-slate-100 text-slate-600 px-2.5 py-1 rounded-lg"><?= htmlspecialchars($soal['bahasa']) ?></span>
                        <?php if ($soal['status'] === 'Diterima'): ?>
                            <span class="inline-flex items-center gap-1.5 font-semibold text-emerald-600 bg-emerald-500/10 border border-emerald-500/30 px-2.5 py-1 rounded-lg">
                                <i class="fa-solid fa-circle-check"></i> Diterima
                            </span>
                        <?php else: ?>
                            <span class="inline-flex items-center gap-1.5 font-semibold text-rose-500 bg-rose-500/10 border border-rose-500/30 px-2.5 py-1 rounded-lg">
                                <i class="fa-solid fa-triangle-exclamation"></i> <?= htmlspecialchars($soal['status']) ?>
                            </span>
                        <?php endif; ?>
                        <span class="text-sub-title transition-colors ml-auto">Skor: <b><?= $soal['skor'] ?></b> · Submit <?= date('d M, H:i', strtotime($soal['waktu_submit'])) ?></span>
                    </div>
                    <pre class="hljs"><code class="language-<?= strtolower($soal['bahasa']) === 'php' ? 'php' : 'javascript' ?>"><?= htmlspecialchars($soal['kode']) ?></code></pre>
                </div>
            </div>

            <!-- Rekaman Pengawasan -->
            <div class="space-y-4">
                <div class="side-card bg-white border border-slate-200 rounded-2xl shadow-xl p-5 transition-colors">
                    <h3 class="font-bold text-main-title transition-colors text-sm mb-3 flex items-center gap-2">
                        <i class="fa-solid fa-video text-indigo-500"></i> Rekaman Kamera
                    </h3>
                    <!-- TODO: ganti dengan <video> beneran + src dari storage rekaman -->
                    <div class="placeholder-box aspect-video w-full bg-slate-50 border border-dashed border-slate-300 rounded-xl flex flex-col items-center justify-center text-center gap-2 transition-colors">
                        <i class="fa-solid fa-camera text-2xl opacity-60"></i>
                        <p class="text-[11px] text-slate-400 px-4">Rekaman kamera belum terhubung — placeholder, menunggu integrasi modul proctoring.</p>
                    </div>
                    <p class="text-[11px] text-sub-title mt-2 transition-colors">Durasi: <?= $durasi_menit ?> menit (dummy)</p>
                </div>

                <div class="side-card bg-white border border-slate-200 rounded-2xl shadow-xl p-5 transition-colors">
                    <h3 class="font-bold text-main-title transition-colors text-sm mb-3 flex items-center gap-2">
                        <i class="fa-solid fa-microphone text-indigo-500"></i> Rekaman Audio
                    </h3>
                    <!-- TODO: ganti dengan <audio> beneran + src dari storage rekaman -->
                    <div class="placeholder-box w-full bg-slate-50 border border-dashed border-slate-300 rounded-xl flex flex-col items-center justify-center text-center gap-2 py-6 transition-colors">
                        <i class="fa-solid fa-waveform-lines text-2xl opacity-60"></i>
                        <p class="text-[11px] text-slate-400 px-4">Rekaman audio belum terhubung — placeholder.</p>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
    <script>
        hljs.highlightAll();

        function togglePower() {
            const body = document.getElementById('main-body');
            const speechText = document.getElementById('speech-text');
            const isOn = body.classList.toggle('power-on');
            speechText.textContent = isOn ? 'Klik untuk pindah ke Light Mode' : 'Klik untuk pindah ke Dark Mode';
        }
    </script>
</body>
</html>