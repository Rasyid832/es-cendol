<?php
// views/lecturer/room_submissions.php
session_start();
require_once __DIR__ . '/../../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'lecturer') {
    header("Location: ../auth/login.php");
    exit();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$lecturer_id = $_SESSION['user_id'];
$room_id     = (int) ($_GET['id'] ?? 0);
$grade_error = $_SESSION['grade_error'] ?? null;
unset($_SESSION['grade_error']);

try {
    $stmtRoom = $pdo->prepare("SELECT * FROM rooms WHERE id = :id AND lecturer_id = :lecturer_id LIMIT 1");
    $stmtRoom->execute(['id' => $room_id, 'lecturer_id' => $lecturer_id]);
    $room = $stmtRoom->fetch(PDO::FETCH_ASSOC);

    if (!$room) {
        header("Location: dashboard.php");
        exit();
    }

    $stmtSubs = $pdo->prepare("
        SELECT s.*, u.name AS student_name, u.identity_number
        FROM sessions s
        JOIN users u ON u.id = s.student_id
        WHERE s.room_id = :room_id
        ORDER BY s.status = 'completed' DESC, s.finished_at DESC, s.joined_at DESC
    ");
    $stmtSubs->execute(['room_id' => $room_id]);
    $submissions = $stmtSubs->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('[VIEW ROOM ERROR] ' . $e->getMessage());
    $room = null;
    $submissions = [];
}

function statusBadge($status) {
    $map = [
        'ongoing'   => ['bg-amber-500/10 text-amber-500 border-amber-500/20', 'Sedang Mengerjakan'],
        'completed' => ['bg-emerald-500/10 text-emerald-500 border-emerald-500/20', 'Selesai'],
        'forfeited' => ['bg-red-500/10 text-red-500 border-red-500/20', 'Gugur'],
    ];
    [$class, $label] = $map[$status] ?? ['bg-slate-500/10 text-slate-500 border-slate-500/20', $status];
    return "<span class=\"px-2.5 py-1 rounded-full text-[11px] font-semibold border {$class}\">{$label}</span>";
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hasil Kerja Siswa - <?= htmlspecialchars($room['subject_name'] ?? '') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Fira+Code:wght@400;600&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; background-color: #0b1120; }
        .card { background-color: #111a2e; border: 1px solid #1e293b; }
        pre.code-view { font-family: 'Fira Code', monospace; }
    </style>
</head>
<body class="min-h-screen text-slate-200">
    <main class="max-w-6xl mx-auto px-5 md:px-8 py-8">
        <a href="dashboard.php" class="text-xs text-indigo-400 hover:text-indigo-300 mb-4 inline-flex items-center gap-1">
            <i class="fa-solid fa-arrow-left"></i> Kembali ke Dashboard
        </a>

        <h1 class="text-xl font-bold text-white mt-2"><?= htmlspecialchars($room['subject_name'] ?? 'Room tidak ditemukan') ?></h1>
        <p class="text-xs text-slate-500 mb-6">
            <?= htmlspecialchars($room['class_name'] ?? '') ?> &bull; Kode Room: <?= htmlspecialchars($room['room_code'] ?? '') ?>
            &bull; Durasi <?= (int) ($room['duration'] ?? 0) ?> menit
        </p>

        <?php if ($grade_error): ?>
            <p class="text-xs text-red-400 bg-red-500/10 border border-red-500/30 px-3 py-2 rounded-lg mb-4"><?= htmlspecialchars($grade_error) ?></p>
        <?php endif; ?>

        <?php if (empty($submissions)): ?>
            <div class="card rounded-2xl p-10 text-center text-slate-500 text-sm">
                <i class="fa-regular fa-folder-open text-2xl mb-2 block"></i>
                Belum ada siswa yang bergabung ke room ini.
            </div>
        <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($submissions as $sub): ?>
                    <div class="card rounded-2xl p-5">
                        <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
                            <div>
                                <p class="text-sm font-semibold text-white"><?= htmlspecialchars($sub['student_name']) ?></p>
                                <p class="text-[11px] text-slate-500"><?= htmlspecialchars($sub['identity_number'] ?? '-') ?></p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-[11px] text-slate-500">Pelanggaran: <?= (int) $sub['violation_count'] ?>/3</span>
                                <?= statusBadge($sub['status']) ?>
                            </div>
                        </div>

                        <?php if (!empty($sub['submitted_code'])): ?>
                            <div class="bg-[#0b1120] border border-slate-800 rounded-xl overflow-hidden mb-3">
                                <div class="flex items-center justify-between px-3 py-1.5 border-b border-slate-800">
                                    <span class="text-[11px] text-slate-500 uppercase tracking-wide"><?= htmlspecialchars($sub['submitted_language'] ?? 'code') ?></span>
                                </div>
                                <pre class="code-view text-xs text-slate-300 p-3 overflow-x-auto max-h-64 overflow-y-auto"><?= htmlspecialchars($sub['submitted_code']) ?></pre>
                            </div>
                        <?php else: ?>
                            <p class="text-xs text-slate-600 italic mb-3">Belum ada kode yang dikumpulkan.</p>
                        <?php endif; ?>

                        <form action="../../controllers/lecturer/save_score.php" method="POST" class="flex items-center gap-2">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                            <input type="hidden" name="session_id" value="<?= (int) $sub['id'] ?>">
                            <input type="hidden" name="room_id" value="<?= $room_id ?>">
                            <label class="text-xs text-slate-400">Nilai:</label>
                            <input type="number" name="score" min="0" max="100" step="0.5"
                                   value="<?= $sub['score'] !== null ? htmlspecialchars($sub['score']) : '' ?>"
                                   placeholder="0-100"
                                   class="bg-[#0b1120] border border-slate-700 rounded-lg px-3 py-1.5 text-sm text-white w-24 focus:border-indigo-500 outline-none">
                            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold px-4 py-1.5 rounded-lg transition">
                                Simpan Nilai
                            </button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>