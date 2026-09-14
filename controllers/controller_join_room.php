<?php
// Aktifkan error reporting agar pesan kesalahan tampil jika terjadi kendala
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// Proteksi Halaman: Wajib login & role 'lecturer'
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'lecturer') {
    header("Location: ../views/auth/login.php");
    exit();
}

// Panggil file koneksi database
require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $room_code = trim($_POST['room_id'] ?? '');
    $nip       = trim($_POST['nip'] ?? '');

    if (empty($room_code)) {
        $_SESSION['error'] = "Room ID wajib diisi!";
        header("Location: ../views/lecturer/join_room.php");
        exit();
    }

    try {
        // Query disesuaikan dengan nama tabel 'rooms'
        $stmt = $pdo->prepare("SELECT * FROM rooms WHERE room_code = :room_code LIMIT 1");
        $stmt->execute(['room_code' => $room_code]);
        $room = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$room) {
            $_SESSION['error'] = "Room ID ujian '$room_code' tidak ditemukan!";
            header("Location: ../views/lecturer/join_room.php");
            exit();
        }

        // Cek jika room berada dalam status arsip
        if ($room['status'] === 'archived') {
            $_SESSION['error'] = "Room ujian '$room_code' sedang diarsipkan. Pulihkan room terlebih dahulu untuk masuk.";
            header("Location: ../views/lecturer/join_room.php");
            exit();
        }

        // Simpan Room ID aktif ke Session
        $_SESSION['active_room_id'] = $room['id'];

        // Redirect ke halaman pengawasan
        header("Location: ../views/lecturer/pengawasan.php?id=" . $room['id']);
        exit();

    } catch (PDOException $e) {
        $_SESSION['error'] = "Terjadi kesalahan database: " . $e->getMessage();
        header("Location: ../views/lecturer/join_room.php");
        exit();
    }
} else {
    header("Location: ../views/lecturer/dashboard.php");
    exit();
}