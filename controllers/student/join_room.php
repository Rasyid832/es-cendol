<?php
// controllers/student/join_room.php
session_start();
require_once __DIR__ . '/../../config/db.php';

// Proteksi: wajib login & role 'student'
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../../views/auth/login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../../views/student/dashboard.php");
    exit();
}

// Verifikasi CSRF token
$sentToken = $_POST['csrf_token'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $sentToken)) {
    http_response_code(403);
    die('Permintaan ditolak: token keamanan tidak valid. Silakan muat ulang halaman.');
}

$room_code  = trim($_POST['room_code'] ?? '');
$student_id = $_SESSION['user_id'];

if (empty($room_code)) {
    $_SESSION['join_error'] = "ID Room wajib diisi.";
    header("Location: ../../views/student/dashboard.php");
    exit();
}

try {
    $stmt = $pdo->prepare("SELECT * FROM rooms WHERE room_code = :code LIMIT 1");
    $stmt->execute(['code' => $room_code]);
    $room = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$room) {
        $_SESSION['join_error'] = "Room dengan ID '{$room_code}' tidak ditemukan.";
        header("Location: ../../views/student/dashboard.php");
        exit();
    }

    if ($room['status'] !== 'active') {
        $_SESSION['join_error'] = "Room ujian ini sedang tidak aktif. Hubungi pengawas ujian Anda.";
        header("Location: ../../views/student/dashboard.php");
        exit();
    }

    // Cek riwayat sesi siswa ini di room tsb (apapun statusnya)
    $stmtCheck = $pdo->prepare("
        SELECT id, status FROM sessions
        WHERE room_id = :room_id AND student_id = :student_id
        ORDER BY id DESC LIMIT 1
    ");
    $stmtCheck->execute(['room_id' => $room['id'], 'student_id' => $student_id]);
    $existing = $stmtCheck->fetch(PDO::FETCH_ASSOC);

    if ($existing && $existing['status'] === 'forfeited') {
        $_SESSION['join_error'] = "Anda sudah keluar dari ujian ini sebelumnya. Ujian dinyatakan gugur dan tidak dapat diakses lagi. Hubungi pengawas ujian jika ini kesalahan.";
        header("Location: ../../views/student/dashboard.php");
        exit();
    }

    if ($existing && $existing['status'] === 'completed') {
        $_SESSION['join_error'] = "Anda sudah menyelesaikan ujian ini sebelumnya.";
        header("Location: ../../views/student/dashboard.php");
        exit();
    }

    if (!$existing) {
        $stmtInsert = $pdo->prepare("
            INSERT INTO sessions (room_id, student_id, status)
            VALUES (:room_id, :student_id, 'ongoing')
        ");
        $stmtInsert->execute(['room_id' => $room['id'], 'student_id' => $student_id]);
    }

    $_SESSION['active_room_id'] = $room['id'];
    header("Location: ../../views/student/exam.php?room_id=" . $room['id']);
    exit();

} catch (PDOException $e) {
    error_log('[STUDENT JOIN ROOM ERROR] ' . $e->getMessage());
    $_SESSION['join_error'] = "Terjadi kesalahan pada server. Silakan coba lagi.";
    header("Location: ../../views/student/dashboard.php");
    exit();
}