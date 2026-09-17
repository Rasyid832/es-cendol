<?php
// controllers/student/report_violation.php
// Dipanggil via fetch() dari views/student/exam.php setiap kali terdeteksi
// pelanggaran (pindah tab, minimize, keluar fullscreen). Setelah melewati
// batas MAX_VIOLATIONS, sesi otomatis digugurkan (forfeited).

header('Content-Type: application/json');
session_start();
require_once __DIR__ . '/../../config/db.php';

const MAX_VIOLATIONS = 3;

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Tidak diizinkan.']);
    exit();
}

$sentToken = $_POST['csrf_token'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $sentToken)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Token CSRF tidak valid.']);
    exit();
}

$room_id    = (int) ($_POST['room_id'] ?? 0);
$student_id = $_SESSION['user_id'];
$reason     = trim($_POST['reason'] ?? 'unknown');

if (!$room_id) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'room_id tidak valid.']);
    exit();
}

try {
    $stmt = $pdo->prepare("
        SELECT id, status, violation_count FROM sessions
        WHERE room_id = :room_id AND student_id = :student_id
        ORDER BY id DESC LIMIT 1
    ");
    $stmt->execute(['room_id' => $room_id, 'student_id' => $student_id]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$session || $session['status'] !== 'ongoing') {
        echo json_encode(['ok' => false, 'forfeited' => true, 'message' => 'Sesi ujian tidak aktif.']);
        exit();
    }

    $newCount = (int) $session['violation_count'] + 1;

    if ($newCount >= MAX_VIOLATIONS) {
        $stmtUpdate = $pdo->prepare("
            UPDATE sessions
            SET violation_count = :count, status = 'forfeited', finished_at = NOW()
            WHERE id = :id
        ");
        $stmtUpdate->execute(['count' => $newCount, 'id' => $session['id']]);
        error_log("[EXAM VIOLATION] student_id={$student_id} room_id={$room_id} reason={$reason} -> FORFEITED");

        echo json_encode([
            'ok'         => true,
            'forfeited'  => true,
            'count'      => $newCount,
            'max'        => MAX_VIOLATIONS,
            'message'    => 'Batas pelanggaran terlampaui. Ujian dinyatakan gugur.',
        ]);
    } else {
        $stmtUpdate = $pdo->prepare("UPDATE sessions SET violation_count = :count WHERE id = :id");
        $stmtUpdate->execute(['count' => $newCount, 'id' => $session['id']]);
        error_log("[EXAM VIOLATION] student_id={$student_id} room_id={$room_id} reason={$reason} count={$newCount}");

        echo json_encode([
            'ok'        => true,
            'forfeited' => false,
            'count'     => $newCount,
            'max'       => MAX_VIOLATIONS,
            'message'   => 'Pelanggaran tercatat.',
        ]);
    }
} catch (PDOException $e) {
    error_log('[REPORT VIOLATION ERROR] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Server error.']);
}