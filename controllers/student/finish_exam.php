<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
session_start();
require_once __DIR__ . '/../../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../../views/auth/login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../../views/student/dashboard.php");
    exit();
}

$sentToken = $_POST['csrf_token'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $sentToken)) {
    http_response_code(403);
    die('Token CSRF tidak valid atau sesi kadaluarsa.');
}

$user_id   = $_SESSION['user_id'];
$room_id   = isset($_POST['room_id']) ? (int)$_POST['room_id'] : (int)($_SESSION['room_id'] ?? 0);

$submitted_code   = $_POST['submitted_code'] ?? $_POST['answer_code'] ?? '';
$language         = $_POST['submitted_language'] ?? $_POST['language'] ?? 'python';
$flight_time_json = $_POST['flight_time_data'] ?? '[]';

try {
    $stmtSession = $pdo->prepare("
        SELECT id FROM sessions 
        WHERE room_id = :room_id AND student_id = :student_id AND status = 'ongoing'
        ORDER BY id DESC LIMIT 1
    ");
    $stmtSession->execute(['room_id' => $room_id, 'student_id' => $user_id]);
    $exam_session = $stmtSession->fetch(PDO::FETCH_ASSOC);

    if ($exam_session) {
        $session_id_for_log = $exam_session['id'];

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

        $insert_query = "INSERT INTO telemetry_logs (session_id, flight_time_data) VALUES (:session_id, :flight_time_data)";
        $stmt_insert = $pdo->prepare($insert_query);
        $stmt_insert->execute([
            'session_id'       => $session_id_for_log,
            'flight_time_data' => $payload_data
        ]);

        $stmtUpdateSession = $pdo->prepare("
            UPDATE sessions 
            SET status = 'completed', 
                submitted_code = :submitted_code,
                submitted_language = :submitted_language,
                finished_at = NOW() 
            WHERE id = :session_id AND status = 'ongoing'
        ");
        $stmtUpdateSession->execute([
            'submitted_code'     => $submitted_code,
            'submitted_language' => $language,
            'session_id'         => $session_id_for_log
        ]);
    }

    $_SESSION['join_error'] = null;
    unset($_SESSION['active_room_id']);
    $_SESSION['success_msg'] = "Ujian berhasil disubmit dan data tersimpan!";

} catch (PDOException $e) {
    error_log('[FINISH EXAM ERROR] ' . $e->getMessage());
    $_SESSION['join_error'] = "Terjadi kendala saat menyimpan ujian, tetapi status Anda telah diamankan.";
}

header("Location: ../../views/student/dashboard.php");
exit();