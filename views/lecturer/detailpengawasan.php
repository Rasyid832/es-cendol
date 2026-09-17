<?php
session_start();

$host = "localhost";
$user = "root";
$pass = "";
$dbname = "codeprocess_db";

$conn = @new mysqli($host,$user, $pass,$dbname);

$student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;
$room_id = isset($_GET['room_id']) ? intval($_GET['room_id']) : 0;

$student_name = "Siswa";
$identity_number = "N/A";
$room_code = "N/A";

if ($conn && !$conn->connect_error) {
    if ($student_id > 0) {
        $q = $conn->prepare("SELECT name, identity_number FROM users WHERE id = ? LIMIT 1");
        if ($q) {
            $q->bind_param("i", $student_id);
            $q->execute();
            $res = $q->get_result();
            if ($row = $res->fetch_assoc()) {
                $student_name = $row['name'];
                $identity_number = $row['identity_number'];
            }
            $q->close();
        }
    }

    if ($room_id <= 0 && $student_id > 0) {
        $qSession = $conn->prepare("
            SELECT room_id
            FROM sessions
            WHERE student_id = ?
              AND status IN ('ongoing', 'active', 'started')
            ORDER BY id DESC
            LIMIT 1
        ");
        if ($qSession) {
            $qSession->bind_param("i", $student_id);
            $qSession->execute();
            $resSession = $qSession->get_result();
            if ($rowSession = $resSession->fetch_assoc()) {
                $room_id = (int)$rowSession['room_id'];
            }
            $qSession->close();
        }
    }

    if ($room_id <= 0 && $student_id > 0) {
        $qSession = $conn->prepare("
            SELECT room_id
            FROM sessions
            WHERE student_id = ?
            ORDER BY id DESC
            LIMIT 1
        ");
        if ($qSession) {
            $qSession->bind_param("i", $student_id);
            $qSession->execute();
            $resSession = $qSession->get_result();
            if ($rowSession = $resSession->fetch_assoc()) {
                $room_id = (int)$rowSession['room_id'];
            }
            $qSession->close();
        }
    }

    if ($room_id > 0) {
        $qRoom = $conn->prepare("SELECT * FROM rooms WHERE id = ? LIMIT 1");
        if ($qRoom) {
            $qRoom->bind_param("i", $room_id);
            $qRoom->execute();
            $resRoom = $qRoom->get_result();
            if ($rowRoom = $resRoom->fetch_assoc()) {
                $room_code = $rowRoom['code'] ?? $rowRoom['room_code'] ?? $rowRoom['code_room'] ?? $rowRoom['name'] ?? 'N/A';
            }
            $qRoom->close();
        }
    }
}

if ($student_id <= 0 || $room_id <= 0) {
    die('Data siswa/room tidak ditemukan. Buka detail pengawasan dari Grid setelah siswa masuk ujian.');
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
                <p class="text-xs text-slate-400 font-mono mt-0.5"><?= htmlspecialchars($identity_number) ?> &bull; Sesi ID: #<?= $room_id ?> &bull; Kode Room: <?= htmlspecialchars((string)$room_code) ?></p>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <span class="bg-red-900/30 text-red-400 border border-red-800/40 text-xs px-3 py-1.5 rounded-lg font-medium flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full bg-red-500 animate-pulse"></span> 0 Telemetri Logs
            </span>
            <span id="peer-status" class="bg-slate-800 text-slate-300 border border-slate-700 text-xs px-3 py-1.5 rounded-lg font-mono">Peer: starting...</span>
            <span id="session-status" class="bg-emerald-900/30 text-emerald-400 border border-emerald-800/40 text-xs px-3.5 py-1.5 rounded-lg font-medium flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full bg-emerald-500"></span> Ongoing
            </span>
        </div>
    </div>

    <div class="flex-1 grid grid-cols-12 gap-6 p-6">

        <div class="col-span-8 flex flex-col gap-6">
            <div class="relative bg-[#050A18] rounded-xl border border-slate-800/80 aspect-video overflow-hidden shadow-2xl flex items-center justify-center">
                
                <div class="absolute top-4 left-4 z-20 flex items-center gap-2 bg-black/40 backdrop-blur-md px-3 py-1.5 rounded-lg border border-white/10 text-xs font-semibold tracking-wide text-slate-200">
                    <svg class="w-4 h-4 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 00-2 2z"></path></svg>
                    Live Screen Share
                </div>

                <video id="screen-video" autoplay playsinline muted class="w-full h-full object-contain relative z-10"></video>

                <div id="screen-loading" class="absolute inset-0 flex flex-col items-center justify-center z-0">
                    <div class="w-8 h-8 border-4 border-slate-600 border-t-slate-200 rounded-full animate-spin mb-4"></div>
                    <p class="text-slate-400 text-sm">Menunggu koneksi layar dari murid...</p>
                </div>

                <div class="absolute bottom-4 right-4 z-30 w-52 h-36 bg-black rounded-lg border border-slate-700/80 overflow-hidden shadow-2xl">
                    <div class="absolute top-2 left-2 z-40 bg-black/60 px-2 py-0.5 rounded text-[10px] text-slate-300">Webcam</div>
                    <video id="cam-video" autoplay playsinline muted class="w-full h-full object-cover relative z-10"></video>
                    <div id="cam-loading" class="absolute inset-0 flex items-center justify-center bg-zinc-900 text-slate-500 z-0">
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
                <button onclick="sendWarning('Peringatan! Harap fokus pada layar ujian!')" class="w-full bg-amber-500/10 hover:bg-amber-500/20 text-amber-400 border border-amber-500/30 font-semibold py-3 px-4 rounded-xl text-sm transition-all flex items-center justify-center gap-2 active:scale-95">
                    🔔 Kirim Peringatan Layar
                </button>
                <button onclick="stopExam()" class="w-full bg-red-500/10 hover:bg-red-500/20 text-red-400 border border-red-500/30 font-semibold py-3 px-4 rounded-xl text-sm transition-all flex items-center justify-center gap-2 active:scale-95">
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

            <form onsubmit="sendCustomLog(event)" class="flex gap-2">
                <input type="text" id="log-message" placeholder="Ketik pesan teguran..." class="flex-1 bg-[#0C152E] border border-slate-800 rounded-xl px-4 py-2.5 text-xs text-white focus:outline-none focus:border-indigo-500" required>
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 px-4 py-2.5 rounded-xl text-white transition-all active:scale-95">
                    <svg class="w-4 h-4 transform rotate-45" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path></svg>
                </button>
            </form>
        </div>

    </div>

    <script>
        const studentId = "<?= $student_id ?>";
        const roomId = "<?= $room_id ?>";
        const supervisorPeerID = `codeprocess-supervisor-${studentId}-${roomId}`;

        let supervisorPeer = null;
        let dataConnection = null;
        let activeCamCall = null;
        let activeScreenCall = null;

        const screenVideo = document.getElementById('screen-video');
        const camVideo = document.getElementById('cam-video');
        const screenLoading = document.getElementById('screen-loading');
        const camLoading = document.getElementById('cam-loading');
        const sessionStatus = document.getElementById('session-status');
        const peerStatus = document.getElementById('peer-status');

        function setPeerStatus(text) {
            if (peerStatus) peerStatus.textContent = 'Peer: ' + text;
            console.log('[SUPERVISOR]', text);
        }

        function showScreenStream(remoteStream) {
            if (!remoteStream) return;
            if (screenVideo.srcObject && screenVideo.srcObject !== remoteStream) {
                try { screenVideo.srcObject.getTracks().forEach(t => t.stop()); } catch (e) {}
            }
            screenVideo.srcObject = remoteStream;
            screenVideo.muted = true;
            screenVideo.play().then(() => {
                if (screenLoading) screenLoading.style.display = 'none';
            }).catch(err => console.error('Gagal play screen video:', err));
        }

        function showCamStream(remoteStream) {
            if (!remoteStream) return;
            if (camVideo.srcObject && camVideo.srcObject !== remoteStream) {
                try { camVideo.srcObject.getTracks().forEach(t => t.stop()); } catch (e) {}
            }
            camVideo.srcObject = remoteStream;
            camVideo.muted = true;
            camVideo.play().then(() => {
                if (camLoading) camLoading.style.display = 'none';
            }).catch(err => console.error('Gagal play cam video:', err));
        }

        function setLiveStatus() {
            if (!sessionStatus) return;
            sessionStatus.innerHTML = '<span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span> Live Connected';
            sessionStatus.className = 'bg-emerald-900/30 text-emerald-400 border border-emerald-800/40 text-xs px-3.5 py-1.5 rounded-lg font-medium flex items-center gap-1.5';
        }

        let supervisorRetryTimer = null;
        let supervisorIntentionalClose = false;

        function cleanupSupervisorPeer() {
            supervisorIntentionalClose = true;
            try { if (dataConnection) dataConnection.close(); } catch (e) {}
            try { if (activeCamCall) activeCamCall.close(); } catch (e) {}
            try { if (activeScreenCall) activeScreenCall.close(); } catch (e) {}
            try { if (supervisorPeer && !supervisorPeer.destroyed) supervisorPeer.destroy(); } catch (e) {}
            dataConnection = null;
            activeCamCall = null;
            activeScreenCall = null;
            supervisorPeer = null;
        }

        function retrySupervisorPeer(delay = 1000) {
            if (supervisorRetryTimer || supervisorIntentionalClose) return;
            supervisorRetryTimer = setTimeout(() => {
                supervisorRetryTimer = null;
                if (!supervisorIntentionalClose) initSupervisorPeer();
            }, delay);
        }

        function initSupervisorPeer() {
            supervisorIntentionalClose = false;
            if (supervisorPeer && !supervisorPeer.destroyed) return;

            setPeerStatus('connecting ' + supervisorPeerID);
            supervisorPeer = new Peer(supervisorPeerID, { debug: 2 });

            supervisorPeer.on('open', (id) => {
                console.log('Supervisor Peer siap dengan ID:', id);
                setPeerStatus('READY');
            });

            supervisorPeer.on('call', (call) => {
                const streamType = call.metadata && call.metadata.type
                    ? call.metadata.type
                    : 'unknown';

                console.log('Incoming WebRTC call:', streamType, call.peer, call.metadata);
                setPeerStatus('CALL IN ' + streamType);

                try {
                    call.answer();
                } catch (e) {
                    console.error('Gagal answer call:', e);
                    return;
                }

                call.on('stream', (remoteStream) => {
                    console.log('Remote stream diterima:', streamType, remoteStream);
                    setPeerStatus('STREAM RECEIVED ' + streamType);

                    if (streamType === 'cam') {
                        activeCamCall = call;
                        showCamStream(remoteStream);
                    } else if (streamType === 'screen') {
                        activeScreenCall = call;
                        showScreenStream(remoteStream);
                    } else {
                        console.warn('Tipe stream tidak dikenal:', streamType);
                    }
                });

                call.on('close', () => {
                    console.log('Stream call ditutup:', streamType);
                    if (streamType === 'screen' && activeScreenCall === call) {
                        activeScreenCall = null;
                        if (screenLoading) screenLoading.style.display = 'flex';
                    }
                    if (streamType === 'cam' && activeCamCall === call) {
                        activeCamCall = null;
                        if (camLoading) camLoading.style.display = 'flex';
                    }
                });

                call.on('error', (err) => {
                    console.error('WebRTC call error:', streamType, err);
                });
            });

            supervisorPeer.on('connection', (conn) => {
                console.log('Data connection masuk dari siswa:', conn.peer);
                dataConnection = conn;

                conn.on('open', () => {
                    console.log('DataChannel siswa TERHUBUNG');
                    setLiveStatus();
                    const requestStreams = () => {
                        if (dataConnection !== conn || !conn.open) return;
                        try { conn.send({ type: 'REQUEST_STREAMS' }); } catch (e) {}
                    };
                    requestStreams();
                    setTimeout(requestStreams, 500);
                    setTimeout(requestStreams, 1500);
                    setTimeout(requestStreams, 3000);
                    setTimeout(requestStreams, 5000);
                });

                conn.on('data', (data) => {
                    console.log('Data dari siswa:', data);
                    if (data && data.type === 'PING') {
                        try { conn.send({ type: 'PONG' }); } catch (e) {}
                        return;
                    }
                });

                conn.on('close', () => {
                    if (dataConnection === conn) dataConnection = null;
                    console.log('DataChannel siswa ditutup');
                });

                conn.on('error', (err) => {
                    console.error('DataChannel error:', err);
                });
            });

            supervisorPeer.on('error', (err) => {
                console.error('Supervisor Peer error:', err.type, err);
                setPeerStatus('ERROR ' + err.type);
                if (err.type === 'unavailable-id') {
                    try { if (supervisorPeer && !supervisorPeer.destroyed) supervisorPeer.destroy(); } catch (e) {}
                    supervisorPeer = null;
                    retrySupervisorPeer(1500);
                }
            });

            setInterval(() => {
                if (!dataConnection || !dataConnection.open) return;
                const camLive = camVideo && camVideo.srcObject && camVideo.srcObject.active;
                const screenLive = screenVideo && screenVideo.srcObject && screenVideo.srcObject.active;
                if (!camLive || !screenLive) {
                    try { dataConnection.send({ type: 'REQUEST_STREAMS' }); } catch (e) {}
                }
            }, 2000);

            supervisorPeer.on('disconnected', () => {
                console.warn('Supervisor Peer disconnected, reconnecting...');
                setPeerStatus('reconnecting');
                setTimeout(() => {
                    if (supervisorIntentionalClose) return;
                    if (supervisorPeer && !supervisorPeer.destroyed) {
                        try { supervisorPeer.reconnect(); }
                        catch (e) { supervisorPeer = null; retrySupervisorPeer(1000); }
                    } else {
                        supervisorPeer = null;
                        retrySupervisorPeer(1000);
                    }
                }, 800);
            });
        }

        initSupervisorPeer();

        window.addEventListener('pagehide', cleanupSupervisorPeer);
        window.addEventListener('beforeunload', cleanupSupervisorPeer);

        function sendWarning(text) {
            if (dataConnection && dataConnection.open) {
                dataConnection.send({ type: 'WARNING', message: text });
                alert("Peringatan berhasil dikirim ke siswa!");
            } else {
                alert("Gagal mengirim! Siswa belum terhubung.");
            }
        }

        function sendCustomLog(e) {
            e.preventDefault();
            const input = document.getElementById('log-message');
            const msg = input.value.trim();

            if (msg && dataConnection && dataConnection.open) {
                dataConnection.send({ type: 'WARNING', message: msg });
                alert("Pesan teguran berhasil dikirim!");
                input.value = "";
            } else {
                alert("Gagal mengirim! Siswa belum terhubung.");
            }
        }

        function stopExam() {
            if (confirm("Apakah Anda yakin ingin menghentikan dan mengeluarkan siswa ini dari ujian?")) {
                if (dataConnection && dataConnection.open) {
                    dataConnection.send({ type: 'STOP_EXAM' });
                    alert("Perintah hentikan ujian telah dikirim ke siswa.");
                } else {
                    alert("Gagal! Siswa tidak terhubung.");
                }
            }
        }
    </script>
</body>
</html>