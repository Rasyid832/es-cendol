<?php
// controllers/student/save_cheat_video.php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/db.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'student') {
    echo json_encode(['success' => false, 'msg' => 'Unauthorized']);
    exit();
}

$log_id = isset($_POST['log_id']) ? intval($_POST['log_id']) : 0;

if (!$log_id) {
    echo json_encode(['success' => false, 'msg' => 'Log ID tidak valid.']);
    exit();
}

if (!isset($_FILES['cheat_video']) || $_FILES['cheat_video']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'msg' => 'Gagal menerima file video dari browser.']);
    exit();
}

// Pastikan folder penyimpanan ada
$targetDir = __DIR__ . '/../../assets/cheat_videos/';
if (!file_exists($targetDir)) {
    mkdir($targetDir, 0777, true);
}

$filename = 'evidence_' . $log_id . '_' . time() . '.webm';
$targetFilePath = $targetDir . $filename;
$dbPath = 'assets/cheat_videos/' . $filename;

if (move_uploaded_file($_FILES['cheat_video']['tmp_name'], $targetFilePath)) {
    try {
        // Update kolom media_url di tabel telemetry_logs
        $stmt = $pdo->prepare("UPDATE telemetry_logs SET media_url = :media_url WHERE id = :id");
        $stmt->execute([
            'media_url' => $dbPath,
            'id' => $log_id
        ]);

        echo json_encode(['success' => true, 'msg' => 'Video bukti kecurangan berhasil disimpan!']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'msg' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'msg' => 'Gagal memindahkan file yang di-upload ke folder tujuan.']);
}