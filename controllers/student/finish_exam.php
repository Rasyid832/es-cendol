<?php
// controllers/student/finish_exam.php
session_start();
require_once __DIR__ . '/../../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../../views/auth/login.php");
    exit();
}

$sentToken = $_POST['csrf_token'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $sentToken)) {
    http_response_code(403);
    die('Token CSRF tidak valid.');
}

$room_id    = (int) ($_POST['room_id'] ?? 0);
$student_id = $_SESSION['user_id'];

try {
    $stmt = $pdo->prepare("
        UPDATE sessions
        SET status = 'completed', finished_at = NOW()
        WHERE room_id = :room_id AND student_id = :student_id AND status = 'ongoing'
    ");
    $stmt->execute(['room_id' => $room_id, 'student_id' => $student_id]);

    $_SESSION['join_error'] = null;
    unset($_SESSION['active_room_id']);
} catch (PDOException $e) {
    error_log('[FINISH EXAM ERROR] ' . $e->getMessage());
}

header("Location: ../../views/student/dashboard.php");
exit();