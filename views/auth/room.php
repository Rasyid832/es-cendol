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
                    $_SESSION['room_id']   = $room['id'];
                    $_SESSION['room_code'] = $room['room_code'] ?? $room['code'];

                    header("Location: lembar.php");
                    exit();
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
    <title>Join Room</title>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        }

        body {
            background-color: #f8fafc;
            color: #0f172a;
            min-height: 100vh;
            -webkit-font-smoothing: antialiased;
        }

        .page-header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 76px;
            background: #2563eb;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 40px;
            box-shadow: 0 10px 30px rgba(37, 99, 235, 0.2);
            z-index: 100;
        }

        .user-pill {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            background: #ffffff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #2563eb;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .user-icon {
            width: 22px;
            height: 22px;
            fill: none;
            stroke: #2563eb;
        }

        .username {
            color: #ffffff;
            font-size: 16px;
            font-weight: 600;
            letter-spacing: 0.02em;
            line-height: 1;
        }

        .back-button {
            color: #ffffff;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border: 1px solid rgba(255, 255, 255, 0.4);
            border-radius: 10px;
            transition: 0.2s ease;
            background: rgba(255, 255, 255, 0.1);
        }

        .back-button:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: translateX(-2px);
        }

        .back-button svg {
            width: 20px;
            height: 20px;
            stroke: #ffffff;
        }

        .page-wrapper {
            width: 100%;
            min-height: 100vh;
            padding: 120px 24px 24px;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .minimal-card {
            background: #ffffff;
            width: 100%;
            max-width: 380px;
            padding: 40px;
            border-radius: 16px;
            border: 1.6px solid #bfdbfe;
            box-shadow: 0 10px 25px -5px rgba(37, 99, 235, 0.1);
        }

        .card-header {
            margin-bottom: 32px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .card-header h1 {
            font-size: 24px;
            font-weight: 600;
            letter-spacing: -0.5px;
            color: #1e293b;
            text-align: center;
        }

        .input-field {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-bottom: 20px;
        }

        .input-field label {
            font-size: 13px;
            font-weight: 500;
            color: #475569;
        }

        .input-field input {
            width: 100%;
            padding: 12px 16px;
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            font-size: 15px;
            color: #0f172a;
            transition: all 0.2s ease;
            outline: none;
        }

        .input-field input::placeholder {
            color: #94a3b8;
        }

        .input-field input:focus {
            background: #ffffff;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
        }

        button {
            width: 100%;
            padding: 14px;
            margin-top: 12px;
            background: #2563eb;
            color: #ffffff;
            border: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            transition: background 0.2s ease, transform 0.1s ease;
        }

        button:hover {
            background: #1d4ed8;
        }

        button:active {
            transform: scale(0.98);
        }

        .spinner {
            display: none;
            width: 18px;
            height: 18px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-top-color: #ffffff;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        button.is-loading .btn-text {
            display: none;
        }

        button.is-loading .spinner {
            display: block;
        }

        .message {
            margin-top: 15px;
            padding: 10px 12px;
            border-radius: 8px;
            font-size: 13px;
            text-align: center;
            word-break: break-word;
        }

        .message.success {
            background: #eff6ff;
            color: #1d4ed8;
            border: 1px solid #bfdbfe;
        }

        .message.error {
            background: #fef2f2;
            color: #b91c1c;
            border: 1px solid #fecaca;
        }

        @media (max-width: 600px) {
            .page-header {
                padding: 0 20px;
            }

            .minimal-card {
                padding: 30px 24px;
            }

            .page-wrapper {
                padding-left: 16px;
                padding-right: 16px;
            }

            .username {
                font-size: 14px;
            }
        }
    </style>
</head>

<body>

<header class="page-header">
    <div class="user-pill">
        <div class="user-avatar">
            <svg class="user-icon" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M20 21a8 8 0 0 0-16 0"></path>
                <circle cx="12" cy="7" r="4"></circle>
            </svg>
        </div>

        <span class="username">
            <?= htmlspecialchars($username) ?>
        </span>
    </div>

    <a href="javascript:history.back()" class="back-button" title="Keluar ke halaman sebelumnya">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <line x1="19" y1="12" x2="5" y2="12"></line>
            <polyline points="12 19 5 12 12 5"></polyline>
        </svg>
    </a>
</header>

<div class="page-wrapper">
    <main class="minimal-card">
        <header class="card-header">
            <h1>Join Room</h1>
        </header>

        <form id="joinForm" method="POST">
            <div class="input-field">
                <label for="roomCode">Kode Room</label>
                <input
                    type="text"
                    id="roomCode"
                    name="roomCode"
                    placeholder="Masukkan Kode Room"
                    autocomplete="off"
                    required
                >
            </div>

            <div class="input-field">
                <label for="roomPassword">Password</label>
                <input
                    type="password"
                    id="roomPassword"
                    name="roomPassword"
                    placeholder="Masukkan Password Room"
                    required
                >
            </div>

            <button type="submit" id="joinBtn">
                <span class="btn-text">Join</span>
                <span class="spinner"></span>
            </button>
        </form>

        <?php if ($message !== ""): ?>
            <div class="message <?= $success ? 'success' : 'error' ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>
    </main>
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