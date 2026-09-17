<?php
// controllers/student/finish_exam.php

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
session_start();
require_once __DIR__ . '/../../config/db.php';

// 1. Proteksi Halaman: Wajib login sebagai student (PERBAIKAN: Kurung tutup diperbaiki)
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../../views/auth/login.php");
    exit();
}

// 2. Validasi Metode Request (Harus POST)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../../views/student/dashboard.php");
    exit();
}

// 3. Validasi CSRF Token
$sentToken = $_POST['csrf_token'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $sentToken)) {
    http_response_code(403);
    die('Token CSRF tidak valid atau sesi kadaluarsa.');
}

$user_id   = $_SESSION['user_id'];
$room_id   = isset($_POST['room_id']) ? (int)$_POST['room_id'] : (int)($_SESSION['room_id'] ?? 0);

// Ambil data kiriman dari form editor (DISESUAIKAN dengan name di lembar.php)
$submitted_code   = $_POST['answer_code'] ?? '';
$language         = $_POST['language'] ?? 'python';
$flight_time_json = $_POST['flight_time_data'] ?? '[]';

try {
    // Cari session aktif milik student di room ini
    $stmtSession = $pdo->prepare("
        SELECT id FROM sessions 
        WHERE room_id = :room_id AND student_id = :student_id AND status = 'ongoing'
        ORDER BY id DESC LIMIT 1
    ");
    $stmtSession->execute(['room_id' => $room_id, 'student_id' => $user_id]);
    $exam_session = $stmtSession->fetch(PDO::FETCH_ASSOC);

    if ($exam_session) {
        $session_id_for_log = $exam_session['id'];

        // Format data jeda ketikan & kode ke dalam payload JSON
        $keystroke_arr = json_decode($flight_time_json, true);
        if (!is_array($keystroke_arr)) {
            $keystroke_arr = [];
        }

        $payload_data = json_encode([
            'language'           => $language,
            'code'               => $submitted_code,
            'keystroke_dynamics' => $keystroke_arr,
            'timestamp'          => date('Y-m-d H:i:s')
        ]);

        // Simpan log telemetri / record ketikan ke database
        $insert_query = "INSERT INTO telemetry_logs (session_id, flight_time_data) VALUES (:session_id, :flight_time_data)";
        $stmt_insert = $pdo->prepare($insert_query);
        $stmt_insert->execute([
            'session_id'       => $session_id_for_log,
            'flight_time_data' => $payload_data
        ]);

        // Update status sesi ujian menjadi selesai (completed)
        $stmtUpdateSession = $pdo->prepare("
            UPDATE sessions 
            SET status = 'completed', finished_at = NOW() 
            WHERE id = :session_id AND status = 'ongoing'
        ");
        $stmtUpdateSession->execute(['session_id' => $session_id_for_log]);
    }

    // Bersihkan session terkait room
    $_SESSION['join_error'] = null;
    unset($_SESSION['active_room_id']);
    $_SESSION['success_msg'] = "Ujian berhasil disubmit dan data tersimpan!";

} catch (PDOException $e) {
    // Jika terjadi error database, catat di error log server
    error_log('[FINISH EXAM ERROR] ' . $e->getMessage());
    $_SESSION['join_error'] = "Terjadi kendala saat menyimpan ujian, tetapi status Anda telah diamankan.";
}

// 4. Redirect bersih kembali ke dashboard siswa
header("Location: ../../views/student/dashboard.php");
exit();