<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

require_once __DIR__ . '/../../config/db.php';

$username = $_SESSION['name'] ?? 'User';

try {
    $stmtUser = $pdo->prepare("SELECT name FROM users WHERE id = :id LIMIT 1");
    $stmtUser->execute(['id' => $_SESSION['user_id']]);
    $userDB = $stmtUser->fetch(PDO::FETCH_ASSOC);

    if ($userDB && !empty($userDB['name'])) {
        $username = $userDB['name'];
        $_SESSION['name'] = $userDB['name'];
    }
} catch (PDOException $e) {
}

$message = "";
$success = false;

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $roomCode = trim(preg_replace('/\s+/', ' ', $_POST["roomCode"] ?? ""));
    $roomPassword = trim(preg_replace('/\s+/', '', $_POST["roomPassword"] ?? ""));

    if (empty($roomCode) || empty($roomPassword)) {
        $message = "Kode room dan password wajib diisi.";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM rooms WHERE LOWER(TRIM(room_code)) = LOWER(:room_code) LIMIT 1");
            $stmt->execute(['room_code' => $roomCode]);
            $room = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$room) {
                $message = "Kode room '$roomCode' tidak ditemukan di database.";
            } elseif (isset($room['status']) && $room['status'] !== 'active') {
                $message = "Room ujian ini sedang tidak aktif atau telah diarsip.";
            } else {
                $dbPassword = trim(!empty($room['passcode']) ? $room['passcode'] : ($room['password'] ?? ''));

                $isPasswordValid = (
                    $roomPassword === $dbPassword || 
                    password_verify($roomPassword, $dbPassword) ||
                    strtolower($roomPassword) === strtolower($dbPassword)
                );

                if ($isPasswordValid) {
                    $studentId = $_SESSION['user_id'];
                    $roomId    = $room['id'];

                    // Cek sesi TERAKHIR siswa ini di room tsb, apapun statusnya
                    // (sebelumnya cuma cek status 'ongoing', jadi siswa yang sudah
                    // gugur/forfeited atau sudah completed bisa bikin sesi baru lagi)
                    $stmtCheck = $pdo->prepare("SELECT id, status FROM sessions WHERE room_id = :room_id AND student_id = :student_id ORDER BY id DESC LIMIT 1");
                    $stmtCheck->execute(['room_id' => $roomId, 'student_id' => $studentId]);
                    $existingSession = $stmtCheck->fetch(PDO::FETCH_ASSOC);

                    if ($existingSession && $existingSession['status'] === 'forfeited') {
                        $message = "Anda sudah dinyatakan gugur dari ujian ini karena melanggar aturan (keluar tab/fullscreen 3x). Tidak bisa bergabung lagi.";
                    } elseif ($existingSession && $existingSession['status'] === 'completed') {
                        $message = "Anda sudah menyelesaikan/mengumpulkan ujian ini sebelumnya. Tidak bisa bergabung lagi.";
                    } else {
                        // Belum pernah join sama sekali, ATAU masih 'ongoing' (lanjutkan sesi yang sama)
                        if (!$existingSession) {
                            $stmtInsert = $pdo->prepare("INSERT INTO sessions (room_id, student_id, status) VALUES (:room_id, :student_id, 'ongoing')");
                            $stmtInsert->execute(['room_id' => $roomId, 'student_id' => $studentId]);
                        }

                        $_SESSION['room_id']   = $room['id'];
                        $_SESSION['room_code'] = $room['room_code'] ?? $room['code'];

                        header("Location: lembar.php?room_id=" . $room['id']);
                        exit();
                    }
                } else {
                    $message = "Password yang kamu masukkan salah.";
                }
            }
        } catch (PDOException $e) {
            $message = "Terjadi kesalahan database: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk Room Ujian - CodeProcess</title>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Plus Jakarta Sans', 'Inter', -apple-system, sans-serif;
        }

        body {
            background-color: #f1f5f9;
            color: #0f172a;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 24px;
            -webkit-font-smoothing: antialiased;
        }
        
        .join-wrapper {
            width: 100%;
            max-width: 440px;
        }

        .brand-header {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            margin-bottom: 24px;
        }

        .brand-icon {
            width: 40px;
            height: 40px;
            background: #4f46e5;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
        }

        .brand-text {
            font-size: 22px;
            font-weight: 700;
            color: #0f172a;
            letter-spacing: -0.5px;
        }

        .card {
            background: #ffffff;
            border-radius: 20px;
            padding: 36px 32px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.01);
            border: 1px solid #e2e8f0;
        }

        .card-header {
            text-align: center;
            margin-bottom: 28px;
        }

        .card-header h1 {
            font-size: 20px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 6px;
        }

        .card-header p {
            font-size: 13px;
            color: #64748b;
        }

        .input-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
            margin-bottom: 18px;
        }

        .input-group label {
            font-size: 13px;
            font-weight: 600;
            color: #334155;
        }

        .input-group input {
            width: 100%;
            padding: 12px 16px;
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            font-size: 14px;
            color: #0f172a;
            outline: none;
            transition: all 0.2s ease;
        }

        .input-group input:focus {
            background: #ffffff;
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
        }

        /* --- TOMBOL SUBMIT --- */
        .btn-submit {
            width: 100%;
            padding: 13px;
            background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
            color: #ffffff;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: opacity 0.2s ease, transform 0.1s ease;
            margin-top: 8px;
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.25);
        }

        .btn-submit:hover {
            opacity: 0.95;
        }

        .btn-submit:active {
            transform: scale(0.99);
        }

        .spinner {
            display: none;
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-top-color: #ffffff;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .btn-submit.is-loading .btn-text {
            display: none;
        }

        .btn-submit.is-loading .spinner {
            display: block;
        }

        .alert-msg {
            margin-top: 18px;
            padding: 12px;
            border-radius: 10px;
            font-size: 13px;
            text-align: center;
            background: #fef2f2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }

        /* --- FOOTER ATAS KEMBALI --- */
        .back-link {
            display: block;
            text-align: center;
            margin-top: 20px;
            font-size: 13px;
            color: #64748b;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s;
        }

        .back-link:hover {
            color: #4f46e5;
        }
    </style>
</head>

<body>

    <div class="join-wrapper">
        <div class="brand-header">
            <div class="brand-icon">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                </svg>
            </div>
            <span class="brand-text">CodeProcess</span>
        </div>

        <!-- Kartu Utama -->
        <div class="card">
            <div class="card-header">
                <h1>Masuk Room Ujian</h1>
                <p>Masukkan kode room dan password ujian kamu.</p>
            </div>

            <form id="joinForm" method="POST">
                <div class="input-group">
                    <label for="roomCode">Kode Room</label>
                    <input type="text" id="roomCode" name="roomCode" placeholder="Contoh: ROOM-HRRU" autocomplete="off" required>
                </div>

                <div class="input-group">
                    <label for="roomPassword">Password / Passcode</label>
                    <input type="password" id="roomPassword" name="roomPassword" placeholder="Masukkan Password Room" required>
                </div>

                <button type="submit" id="joinBtn" class="btn-submit">
                    <span class="btn-text">Join Room</span>
                    <span class="spinner"></span>
                </button>
            </form>

            <?php if ($message !== ""): ?>
                <div class="alert-msg">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>
        </div>

        <a href="javascript:history.back()" class="back-link">← Kembali ke Halaman Sebelumnya</a>
    </div>

    <script>
        const joinForm = document.getElementById("joinForm");
        const joinBtn = document.getElementById("joinBtn");

        joinForm.addEventListener("submit", function () {
            joinBtn.classList.add("is-loading");
            joinBtn.disabled = true;
        });
    </script>
</body>
</html>