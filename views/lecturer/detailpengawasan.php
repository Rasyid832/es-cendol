<?php
session_start();

$host = "localhost";
$user = "root";
$pass = "";
$dbname = "codeprocess_db";

$conn = @new mysqli($host, $user, $pass, $dbname);

$student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : 5;
$room_id = isset($_GET['room_id']) ? intval($_GET['room_id']) : 1;

$student_name = "Siswa";
$identity_number = "N/A";

if ($conn && !$conn->connect_error) {
    $q = $conn->prepare("SELECT name, identity_number FROM users WHERE id = ?");
    if ($q) {
        $q->bind_param("i", $student_id);
        $q->execute();
        $res = $q->get_result();
        if ($row = $res->fetch_assoc()) {
            $student_name = $row['name'];
            $identity_number = $row['identity_number'];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Pengawasan - <?= htmlspecialchars($student_name) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/peerjs@1.5.2/dist/peerjs.min.js"></script>
</head>
<body class="bg-[#0B132B] text-white min-h-screen font-sans flex flex-col">

    <div class="px-8 py-5 border-b border-slate-800/60 flex justify-between items-center bg-[#0C152E]">
        <div class="flex items-center gap-6">
            <a href="javascript:history.back()" class="flex items-center gap-2 bg-slate-800/80 hover:bg-slate-700 text-slate-200 px-4 py-2 rounded-lg text-sm font-medium transition-all">
                &larr; Kembali ke Grid
            </a>
            <div>
                <h1 class="text-xl font-bold tracking-wide text-white"><?= htmlspecialchars($student_name) ?></h1>
                <p class="text-xs text-slate-400 font-mono mt-0.5"><?= htmlspecialchars($identity_number) ?> &bull; Sesi ID: #<?= $room_id ?> &bull; Kode Room: N/A</p>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <span class="bg-red-900/30 text-red-400 border border-red-800/40 text-xs px-3 py-1.5 rounded-lg font-medium flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full bg-red-500 animate-pulse"></span> 0 Telemetri Logs
            </span>
            <span class="bg-emerald-900/30 text-emerald-400 border border-emerald-800/40 text-xs px-3.5 py-1.5 rounded-lg font-medium flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full bg-emerald-500"></span> Ongoing
            </span>
        </div>
    </div>

    <div class="flex-1 grid grid-cols-12 gap-6 p-6">

        <div class="col-span-8 flex flex-col gap-6">
            
            <div class="relative bg-[#050A18] rounded-xl border border-slate-800/80 aspect-video overflow-hidden shadow-2xl flex items-center justify-center">
                
                <div class="absolute top-4 left-4 z-20 flex items-center gap-2 bg-black/40 backdrop-blur-md px-3 py-1.5 rounded-lg border border-white/10 text-xs font-semibold tracking-wide text-slate-200">
                    <svg class="w-4 h-4 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                    Live Screen Share
                </div>

                <video id="screen-video" autoplay playsinline class="w-full h-full object-contain relative z-10"></video>

                <div id="screen-loading" class="absolute inset-0 flex flex-col items-center justify-center z-0">
                    <div class="w-8 h-8 border-4 border-slate-600 border-t-slate-200 rounded-full animate-spin mb-4"></div>
                    <p class="text-slate-400 text-sm">Menunggu koneksi layar dari murid...</p>
                </div>

                <div class="absolute bottom-4 right-4 z-30 w-52 h-36 bg-black rounded-lg border border-slate-700/80 overflow-hidden shadow-2xl">
                    <div class="absolute top-2 left-2 z-40 bg-black/60 px-2 py-0.5 rounded text-[10px] text-slate-300">Webcam</div>
                    <video id="cam-video" autoplay playsinline class="w-full h-full object-cover"></video>
                    <div id="cam-loading" class="absolute inset-0 flex items-center justify-center bg-zinc-900 text-slate-500">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                    </div>
                </div>

            </div>

            <div class="grid grid-cols-3 gap-4">
                <div class="bg-[#0C152E] border border-slate-800/80 rounded-xl p-4 text-center">
                    <p class="text-xs text-slate-400 font-medium mb-1">Waktu Masuk</p>
                    <p class="text-lg font-bold text-emerald-400 font-mono">13:22:58</p>
                </div>
                <div class="bg-[#0C152E] border border-slate-800/80 rounded-xl p-4 text-center">
                    <p class="text-xs text-slate-400 font-medium mb-1">Total Telemetri Record</p>
                    <p class="text-lg font-bold text-white font-mono">0</p>
                </div>
                <div class="bg-[#0C152E] border border-slate-800/80 rounded-xl p-4 text-center">
                    <p class="text-xs text-slate-400 font-medium mb-1">Status Sesi</p>
                    <p class="text-lg font-bold text-blue-400 tracking-wider">ONGOING</p>
                </div>
            </div>

        </div>

        <div class="col-span-4 flex flex-col gap-5">
            
            <div class="flex flex-col gap-3">
                <button class="w-full bg-amber-500/10 hover:bg-amber-500/20 text-amber-400 border border-amber-500/30 font-semibold py-3 px-4 rounded-xl text-sm transition-all flex items-center justify-center gap-2">
                    🔔 Kirim Peringatan Layar
                </button>
                <button class="w-full bg-red-500/10 hover:bg-red-500/20 text-red-400 border border-red-500/30 font-semibold py-3 px-4 rounded-xl text-sm transition-all flex items-center justify-center gap-2">
                    🚫 Hentikan Ujian Siswa
                </button>
            </div>

            <div class="bg-[#0C152E] border border-slate-800/80 rounded-xl p-5 flex-1 flex flex-col">
                <h3 class="text-sm font-semibold text-slate-200 mb-4">Telemetry Logs Stream</h3>
                <div class="flex-1 bg-[#050A18] border border-slate-800/60 rounded-xl p-4 flex items-center justify-center text-center">
                    <p class="text-xs text-red-400/80 leading-relaxed max-w-[200px]">
                        ℹ️ Belum ada rekaman telemetri untuk sesi ini.
                    </p>
                </div>
            </div>

            <div class="flex gap-2">
                <input type="text" placeholder="Ketik pesan teguran..." class="flex-1 bg-[#0C152E] border border-slate-800 rounded-xl px-4 py-2.5 text-xs text-white focus:outline-none focus:border-indigo-500">
                <button class="bg-indigo-600 hover:bg-indigo-700 px-4 py-2.5 rounded-xl text-white transition-all">
                    <svg class="w-4 h-4 transform rotate-45" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path></svg>
                </button>
            </div>

        </div>

    </div>

    <script>
        const studentId = "<?= $student_id ?>";
        const roomId = "<?= $room_id ?>";
        
        const targetScreenID = `codeprocess-screen-${studentId}-${roomId}`;
        const targetCamID = `codeprocess-cam-${studentId}-${roomId}`;

        const supervisorPeer = new Peer();

        supervisorPeer.on('open', () => {
            connectStreams();
        });

        function connectStreams() {
            const screenCall = supervisorPeer.call(targetScreenID, createDummyStream());
            if (screenCall) {
                screenCall.on('stream', remoteStream => {
                    document.getElementById('screen-loading').style.display = 'none';
                    document.getElementById('screen-video').srcObject = remoteStream;
                });
            }

            const camCall = supervisorPeer.call(targetCamID, createDummyStream());
            if (camCall) {
                camCall.on('stream', remoteStream => {
                    document.getElementById('cam-loading').style.display = 'none';
                    document.getElementById('cam-video').srcObject = remoteStream;
                });
            }
        }

        function createDummyStream() {
            const canvas = document.createElement('canvas');
            canvas.width = 1;
            canvas.height = 1;
            return canvas.captureStream();
        }

        setInterval(connectStreams, 5000);
    </script>
</body>
</html>