<?php
session_start();
require_once __DIR__ . '/../config/db.php';

// Validasi Akses: Hanya Dosen yang boleh mengakses
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'lecturer') {
    header("Location: ../views/auth/login.php");
    exit();
}

// Verifikasi CSRF token (untuk aksi yang mengubah/menghapus data)
$sentToken = $_GET['csrf_token'] ?? $_POST['csrf_token'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $sentToken)) {
    http_response_code(403);
    die('Permintaan ditolak: token keamanan tidak valid. Silakan muat ulang halaman.');
}

$action = $_GET['action'] ?? '';
$room_id = $_GET['room_id'] ?? $_GET['id'] ?? '';
$lecturer_id = $_SESSION['user_id'];
$from = $_GET['from'] ?? 'history';

if (!$room_id) {
    header("Location: ../views/lecturer/history.php");
    exit();
}

try {
    if ($action === 'toggle_status') {
        $stmt = $pdo->prepare("
            UPDATE rooms 
            SET status = IF(status = 'active', 'inactive', 'active') 
            WHERE id = :id AND lecturer_id = :lecturer_id
        ");
        $stmt->execute(['id' => $room_id, 'lecturer_id' => $lecturer_id]);
        $_SESSION['success'] = "Status akses room ujian berhasil diperbarui.";

    } elseif ($action === 'archive') {
        $stmt = $pdo->prepare("
            UPDATE rooms 
            SET status = 'archived' 
            WHERE id = :id AND lecturer_id = :lecturer_id
        ");
        $stmt->execute(['id' => $room_id, 'lecturer_id' => $lecturer_id]);
        $_SESSION['success'] = "Room berhasil dipindahkan ke Arsip Ujian.";

    } elseif ($action === 'restore') {
        $stmt = $pdo->prepare("
            UPDATE rooms 
            SET status = 'inactive' 
            WHERE id = :id AND lecturer_id = :lecturer_id
        ");
        $stmt->execute(['id' => $room_id, 'lecturer_id' => $lecturer_id]);
        $_SESSION['success'] = "Room berhasil dipulihkan dari Arsip.";

    } elseif ($action === 'delete') {
        $stmt = $pdo->prepare("
            DELETE FROM rooms 
            WHERE id = :id AND lecturer_id = :lecturer_id
        ");
        $stmt->execute(['id' => $room_id, 'lecturer_id' => $lecturer_id]);
        $_SESSION['success'] = "Room ujian beserta seluruh log bukti berhasil dihapus permanen.";
    }
} catch (PDOException $e) {
    $_SESSION['error'] = "Gagal memproses aksi: " . $e->getMessage();
}

// Redirect aman melewati views/lecturer/
$targets = ['archive' => 'archive.php', 'dashboard' => 'dashboard.php', 'history' => 'history.php'];
$target_filename = $targets[$from] ?? 'history.php';
header("Location: ../views/lecturer/" . $target_filename);
exit();