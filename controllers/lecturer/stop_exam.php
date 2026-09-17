<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'lecturer') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Akses ditolak.']);
    exit();
}

$student_id = isset($_POST['student_id']) ? (int)$_POST['student_id'] : 0;
$room_id = isset($_POST['room_id']) ? (int)$_POST['room_id'] : 0;

if ($student_id <= 0 || $room_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Data siswa atau room tidak valid.']);
    exit();
}

require_once __DIR__ . '/../../config/db.php';

try {
    $stmt = $pdo->prepare("
        UPDATE sessions
        SET status = 'forfeited'
        WHERE student_id = :student_id
          AND room_id = :room_id
          AND status = 'ongoing'
    ");
    $stmt->execute([
        'student_id' => $student_id,
        'room_id' => $room_id
    ]);

    echo json_encode([
        'success' => true,
        'updated' => $stmt->rowCount()
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Gagal memperbarui status sesi.']);
}
