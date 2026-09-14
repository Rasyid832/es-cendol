<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'lecturer') {
    header("Location: ../views/auth/login.php");
    exit();
}

require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lecturer_id = $_SESSION['user_id'];
    
    $subject_name = trim($_POST['exam_name'] ?? '');
    $class_name   = trim($_POST['class'] ?? '');
    $duration     = (int)($_POST['duration'] ?? 60);
    $room_code    = trim($_POST['room_code'] ?? '');
    $description  = trim($_POST['description'] ?? '');
    $passcode     = trim($_POST['passcode'] ?? '');

    if (empty($subject_name) || empty($room_code) || empty($description) || empty($passcode)) {
        $_SESSION['error'] = 'Harap isi semua kolom input yang wajib!';
        header("Location: ../views/lecturer/create_room.php");
        exit();
    }

    try {
        // Menggunakan tabel 'rooms' dan penyesuaian kolom sesuai skema database baru
        $sql = "INSERT INTO rooms (lecturer_id, subject_name, class_name, room_code, passcode, duration, description, status) 
                VALUES (:lecturer_id, :subject_name, :class_name, :room_code, :passcode, :duration, :description, 'active')";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'lecturer_id'  => $lecturer_id,
            'subject_name' => $subject_name,
            'class_name'   => $class_name,
            'room_code'    => $room_code,
            'passcode'     => $passcode,
            'duration'     => $duration,
            'description'  => $description
        ]);

        $new_room_id = $pdo->lastInsertId();

        header("Location: ../views/lecturer/pengawasan.php?id=" . $new_room_id);
        exit();

    } catch (PDOException $e) {
        $_SESSION['error'] = "Gagal membuat room: " . $e->getMessage();
        header("Location: ../views/lecturer/create_room.php");
        exit();
    }
} else {
    header("Location: ../views/lecturer/dashboard.php");
    exit();
}