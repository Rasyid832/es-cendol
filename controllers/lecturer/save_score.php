<?php
// controllers/lecturer/save_score.php
session_start();
require_once __DIR__ . '/../../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'lecturer') {
    header("Location: ../../views/auth/login.php");
    exit();
}

$sentToken = $_POST['csrf_token'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $sentToken)) {
    http_response_code(403);
    die('Token CSRF tidak valid.');
}

$session_id  = (int) ($_POST['session_id'] ?? 0);
$room_id     = (int) ($_POST['room_id'] ?? 0);
$lecturer_id = $_SESSION['user_id'];
$score       = $_POST['score'] ?? '';

if ($score !== '' && (!is_numeric($score) || $score < 0 || $score > 100)) {
    $_SESSION['grade_error'] = "Nilai harus berupa angka 0-100.";
    header("Location: ../../views/lecturer/room_submissions.php?id=" . $room_id);
    exit();
}

try {
    // Pastikan room ini benar milik dosen yang login (jangan sampai bisa nilai punya dosen lain)
    $stmtCheck = $pdo->prepare("SELECT id FROM rooms WHERE id = :room_id AND lecturer_id = :lecturer_id LIMIT 1");
    $stmtCheck->execute(['room_id' => $room_id, 'lecturer_id' => $lecturer_id]);
    if (!$stmtCheck->fetch()) {
        http_response_code(403);
        die('Anda tidak memiliki akses ke room ini.');
    }

    $stmt = $pdo->prepare("
        UPDATE sessions SET score = :score
        WHERE id = :session_id AND room_id = :room_id
    ");
    $stmt->execute([
        'score'      => $score === '' ? null : $score,
        'session_id' => $session_id,
        'room_id'    => $room_id,
    ]);
} catch (PDOException $e) {
    error_log('[SAVE SCORE ERROR] ' . $e->getMessage());
    $_SESSION['grade_error'] = "Gagal menyimpan nilai.";
}

header("Location: ../../views/lecturer/room_submissions.php?id=" . $room_id);
exit();