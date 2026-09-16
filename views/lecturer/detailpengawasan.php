<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Pengawasan - CodeProcess</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Fira+Code:wght@500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --bg-dark: #021024;
            --bg-card: #052659;
            --bg-video: #010a17;
            --blue-accent: #5483B3;
            --blue-light: #7DA0CA;
            --blue-soft: #C1E8FF;
            --white: #FFFFFF;
            --success: #10B981;
            --warning: #F59E0B;
            --danger: #EF4444;
            --border: rgba(125, 160, 202, 0.2);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Poppins', sans-serif; }

        body { 
            background-color: var(--bg-dark); color: var(--white); 
            height: 100vh; display: flex; flex-direction: column; overflow: hidden;
        }

        /* Header Bar */
        .header-bar {
            padding: 1rem 2rem; background: var(--bg-card); 
            border-bottom: 1px solid var(--border);
            display: flex; justify-content: space-between; align-items: center;
        }

        .student-profile { display: flex; align-items: center; gap: 15px; }
        .btn-back {
            background: transparent; color: var(--blue-light); border: 1px solid var(--border);
            padding: 0.5rem 1rem; border-radius: 8px; cursor: pointer; transition: 0.3s;
            display: flex; align-items: center; gap: 8px; font-weight: 500; text-decoration: none;
        }
        .btn-back:hover { background: var(--blue-accent); color: var(--white); }

        .info h2 { font-size: 1.1rem; font-weight: 600; line-height: 1.2; }
        .info p { font-size: 0.8rem; color: var(--blue-soft); }

        .status-badges { display: flex; gap: 10px; }
        .badge {
            padding: 5px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 600;
            display: flex; align-items: center; gap: 6px;
        }
        .badge.danger { background: rgba(239, 68, 68, 0.15); color: var(--danger); border: 1px solid var(--danger); }
        .badge.success { background: rgba(16, 185, 129, 0.15); color: var(--success); border: 1px solid var(--success); }

        /* Main Layout */
        .main-content { display: flex; flex: 1; overflow: hidden; }

        /* Kiri: Monitoring Video */
        .video-section {
            flex: 2.5; padding: 1.5rem; display: flex; flex-direction: column; gap: 1rem;
            background: rgba(2, 16, 36, 0.5); overflow-y: auto;
        }

        .video-container {
            position: relative; width: 100%; aspect-ratio: 16/9;
            background: var(--bg-video); border-radius: 12px; overflow: hidden;
            border: 1px solid var(--blue-accent); box-shadow: 0 10px 30px rgba(0,0,0,0.5);
        }

        .screen-share {
            width: 100%; height: 100%; display: flex; justify-content: center; align-items: center;
            flex-direction: column; color: rgba(255,255,255,0.2); font-size: 1.5rem;
            background: repeating-linear-gradient(45deg, #021024, #021024 10px, #052659 10px, #052659 20px);
        }

        .webcam-pip {
            position: absolute; bottom: 20px; right: 20px; width: 240px; aspect-ratio: 4/3;
            background: #111; border-radius: 8px; border: 2px solid var(--white);
            box-shadow: 0 5px 15px rgba(0,0,0,0.5); overflow: hidden; display: flex;
            justify-content: center; align-items: center; font-size: 2rem; color: rgba(255,255,255,0.3);
        }

        .overlay-label {
            position: absolute; top: 15px; left: 15px; background: rgba(0,0,0,0.6);
            padding: 5px 10px; border-radius: 4px; font-size: 0.8rem; font-weight: 500;
            display: flex; align-items: center; gap: 8px; backdrop-filter: blur(5px);
        }

        .connection-stats {
            display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem;
        }
        .stat-box {
            background: var(--bg-card); padding: 1rem; border-radius: 12px;
            border: 1px solid var(--border); text-align: center;
        }
        .stat-box h4 { font-size: 0.8rem; color: var(--blue-light); margin-bottom: 5px; font-weight: 500;}
        .stat-box .val { font-size: 1.2rem; font-weight: 700; color: var(--blue-soft); font-family: 'Fira Code', monospace;}

        /* Kanan: Sidebar Aksi & Chat */
        .action-section {
            flex: 1; min-width: 320px; background: var(--bg-card);
            border-left: 1px solid var(--border); display: flex; flex-direction: column;
        }

        /* Tombol Aksi Keras */
        .force-actions { padding: 1.5rem; border-bottom: 1px solid var(--border); display: grid; gap: 10px; }
        .btn-action {
            width: 100%; padding: 0.8rem; border-radius: 8px; border: none;
            font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 10px;
            transition: 0.2s;
        }
        .btn-warn { background: rgba(245, 158, 11, 0.15); color: var(--warning); border: 1px solid var(--warning); }
        .btn-warn:hover { background: var(--warning); color: #000; }
        .btn-kick { background: rgba(239, 68, 68, 0.15); color: var(--danger); border: 1px solid var(--danger); }
        .btn-kick:hover { background: var(--danger); color: white; }

        /* Area Chat */
        .chat-area { flex: 1; display: flex; flex-direction: column; overflow: hidden; }
        .chat-header { padding: 1rem 1.5rem; border-bottom: 1px solid var(--border); font-weight: 600; font-size: 0.95rem; }
        
        .chat-messages {
            flex: 1; padding: 1.5rem; overflow-y: auto; display: flex; flex-direction: column; gap: 1rem;
        }

        .msg { max-width: 85%; padding: 0.8rem 1rem; border-radius: 12px; font-size: 0.85rem; line-height: 1.4; }
        .msg.system { background: rgba(239, 68, 68, 0.1); border: 1px solid var(--danger); color: var(--danger); align-self: center; text-align: center; font-size: 0.75rem; width: 100%;}
        .msg.teacher { background: var(--blue-accent); color: white; align-self: flex-end; border-bottom-right-radius: 4px;}
        .msg.student { background: rgba(2, 16, 36, 0.6); border: 1px solid var(--border); align-self: flex-start; border-bottom-left-radius: 4px;}

        .chat-input {
            padding: 1rem 1.5rem; border-top: 1px solid var(--border); background: rgba(2, 16, 36, 0.4);
            display: flex; gap: 10px;
        }
        .chat-input input {
            flex: 1; padding: 0.8rem 1rem; border-radius: 8px; border: 1px solid var(--border);
            background: var(--bg-dark); color: white; outline: none;
        }
        .chat-input button {
            padding: 0 1.2rem; border-radius: 8px; border: none; background: var(--blue-soft);
            color: var(--bg-dark); font-weight: 600; cursor: pointer;
        }
    </style>
</head>
<body>

    <!-- Header / Profil Siswa -->
    <div class="header-bar">
        <div class="student-profile">
            <!-- Link disesuaikan dengan file pengawasan utama -->
            <a href="pengawasan.php" class="btn-back"><i class="fa-solid fa-arrow-left"></i> Kembali ke Grid</a>
            <div style="width: 1px; height: 30px; background: var(--border); margin: 0 10px;"></div>
            <div class="info">
                <!-- Data ini nantinya akan diisi dinamis dari database via Controller -->
                <h2><?= isset($student_name) ? $student_name : 'Endry Julyan Putra' ?></h2>
                <p><?= isset($student_nim) ? $student_nim : '21098492' ?> • <?= isset($student_major) ? $student_major : 'Teknik Informatika' ?> • RM-8492</p>
            </div>
        </div>
        <div class="status-badges">
            <div class="badge danger"><i class="fa-solid fa-triangle-exclamation"></i> <?= isset($violation_count) ? $violation_count : '2' ?> Pelanggaran</div>
            <div class="badge success"><i class="fa-solid fa-wifi"></i> Online</div>
        </div>
    </div>

    <div class="main-content">
        <!-- Kiri: Video & Screen Share -->
        <div class="video-section">
            <div class="video-container">
                <div class="overlay-label"><i class="fa-solid fa-desktop"></i> Live Screen Share</div>
                <div class="screen-share">
                    <i class="fa-solid fa-code" style="font-size: 3rem; margin-bottom: 10px;"></i>
                    <p>Visual Studio Code - <?= isset($active_file) ? $active_file : 'looping.java' ?></p>
                </div>
                
                <!-- PIP Webcam -->
                <div class="webcam-pip">
                    <div style="position: absolute; top: 10px; left: 10px; font-size: 0.6rem; background: rgba(0,0,0,0.5); padding: 3px 6px; border-radius: 4px;">Webcam</div>
                    <i class="fa-solid fa-user-astronaut"></i>
                </div>
            </div>

            <div class="connection-stats">
                <div class="stat-box">
                    <h4>Ping Jaringan</h4>
                    <div class="val" style="color: var(--success);"><?= isset($ping) ? $ping : '24 ms' ?></div>
                </div>
                <div class="stat-box">
                    <h4>Progress Soal</h4>
                    <div class="val"><?= isset($answered_questions) ? $answered_questions : '18' ?> / <?= isset($total_questions) ? $total_questions : '25' ?></div>
                </div>
                <div class="stat-box">
                    <h4>Fokus Tab Browser</h4>
                    <div class="val" style="color: var(--danger);"><?= isset($tab_status) ? $tab_status : 'Di luar ujian' ?></div>
                </div>
            </div>
        </div>

        <!-- Kanan: Aksi & Chat -->
        <div class="action-section">
            <div class="force-actions">
                <button class="btn-action btn-warn"><i class="fa-solid fa-bell"></i> Kirim Peringatan Layar</button>
                <button class="btn-action btn-kick"><i class="fa-solid fa-ban"></i> Hentikan Ujian Siswa</button>
            </div>

            <div class="chat-area">
                <div class="chat-header">Direct Message (Private)</div>
                <div class="chat-messages">
                    <div class="msg system">
                        <i class="fa-solid fa-robot"></i> AI mendeteksi pergantian tab browser pada sesi ini.
                    </div>
                    <div class="msg teacher">
                        <?= isset($student_name) ? explode(' ', trim($student_name))[0] : 'Endry' ?>, sistem mendeteksi Anda membuka tab lain. Harap kembali ke halaman ujian sekarang juga, atau sesi Anda akan saya hentikan.
                    </div>
                    <div class="msg student">
                        Maaf Pak, saya tadi tidak sengaja memencet tombol windows. Saya sudah kembali ke halaman ujian.
                    </div>
                </div>
                <div class="chat-input">
                    <input type="text" placeholder="Ketik pesan teguran...">
                    <button><i class="fa-solid fa-paper-plane"></i></button>
                </div>
            </div>
        </div>
    </div>

</body>
</html>