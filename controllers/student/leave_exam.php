<?php
// controllers/student/leave_exam.php
// Dipanggil via navigator.sendBeacon() saat halaman ujian ditutup/di-navigasi
// keluar. sendBeacon mengirim data sebagai teks mentah, bukan $_POST biasa,
// jadi kita parse manual dari php://input.

session_start();
require_once __DIR__ . '/../../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    http_response_code(204); // beacon tidak butuh response body, cukup diam
    exit();
}

parse_str(file_get_contents('php://input'), $data);

$sentToken = $data['csrf_token'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $sentToken)) {
    http_response_code(204);
    exit();
}

$room_id    = (int) ($data['room_id'] ?? 0);
$student_id = $_SESSION['user_id'];

if (!$room_id) {
    http_response_code(204);
    exit();
}

try {
    $stmt = $pdo->prepare("
        UPDATE sessions
        SET status = 'forfeited', finished_at = NOW()
        WHERE room_id = :room_id AND student_id = :student_id AND status = 'ongoing'
    ");
    $stmt->execute(['room_id' => $room_id, 'student_id' => $student_id]);
    error_log("[EXAM LEFT] student_id={$student_id} room_id={$room_id} -> FORFEITED (tab/page closed)");
} catch (PDOException $e) {
    error_log('[LEAVE EXAM ERROR] ' . $e->getMessage());
}

http_response_code(204);