<?php
if (session_status() === PHP_SESSION_NONE) session_start();

$ROOT = dirname(__DIR__, 2);
foreach (['/config/database.php', '/config/db.php', '/config/koneksi.php'] as $f) {
    if (is_file($ROOT . $f)) { require_once $ROOT . $f; break; }
}

$conn = null; $kind = null;
foreach (['pdo', 'conn', 'koneksi', 'db', 'dbh', 'mysqli'] as $n) {
    if (isset($GLOBALS[$n])) {
        if ($GLOBALS[$n] instanceof PDO)    { $conn = $GLOBALS[$n]; $kind = 'pdo';    break; }
        if ($GLOBALS[$n] instanceof mysqli) { $conn = $GLOBALS[$n]; $kind = 'mysqli'; break; }
    }
}
if (!$conn) {
    $conn = new PDO('mysql:host=localhost;dbname=codeprocess_db;charset=utf8mb4', 'root', '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $kind = 'pdo';
}

function db_all($sql, array $params = []) {
    global $conn, $kind;
    $params = array_values($params);
    if ($kind === 'pdo') {
        $st = $conn->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }
    $st = $conn->prepare($sql);
    if (!$st) throw new RuntimeException($conn->error);
    if ($params) {
        $bind = [str_repeat('s', count($params))];
        foreach ($params as $k => $v) $bind[] = &$params[$k];
        call_user_func_array([$st, 'bind_param'], $bind);
    }
    $st->execute();
    $res  = $st->get_result();
    $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    $st->close();
    return $rows;
}

$userId   = $_SESSION['user_id'] ?? ($_SESSION['id'] ?? null);
$userRole = $_SESSION['role']    ?? null;

if (!$userId) { header('Location: ../auth/login.php'); exit; }
if (!in_array($userRole, ['lecturer', 'admin'], true)) {
    http_response_code(403);
    exit('Halaman ini hanya untuk dosen dan admin.');
}

$selectedRoomId = filter_input(INPUT_GET, 'room_id', FILTER_VALIDATE_INT) ?: null;

$sqlSessions = "SELECT s.id AS session_id, s.status AS session_status, s.violation_count, 
                       s.score, u.name AS student_name, u.identity_number,
                       r.id AS room_id, r.room_code, r.subject_name
                FROM sessions s
                JOIN users u ON u.id = s.student_id
                JOIN rooms r ON r.id = s.room_id
                WHERE 1=1";
$paramsSession = [];

if ($userRole !== 'admin') { 
    $sqlSessions .= " AND r.lecturer_id = ?"; 
    $paramsSession[] = $userId; 
}

if ($selectedRoomId) {
    $sqlSessions .= " AND r.id = ?";
    $paramsSession[] = $selectedRoomId;
}

$sqlSessions .= " ORDER BY s.id DESC";

$error = null; 
$roomsGrouped = [];

try { 
    $rawStudents = db_all($sqlSessions, $paramsSession);

    if ($rawStudents) {
        foreach ($rawStudents as $st) {
            $rId = (string)$st['room_id'];
            if (!isset($roomsGrouped[$rId])) {
                $roomsGrouped[$rId] = [
                    'room_code'    => $st['room_code'],
                    'subject_name' => $st['subject_name'],
                    'students'     => []
                ];
            }
            $roomsGrouped[$rId]['students'][] = $st;
        }
    }
} catch (Throwable $e) { 
    $error = $e->getMessage(); 
}

function esc($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }

function status_badge($st) {
    if ($st === 'completed') return '<span class="badge status-selesai">Selesai</span>';
    if ($st === 'forfeited') return '<span class="badge status-gugur">Gugur / Dikeluarkan</span>';
    return '<span class="badge status-proses">Sedang Mengerjakan</span>';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Log Ujian Siswa - CodeProcess</title>
<style>
  :root{
    --hijau:#1F6F4A; --kertas:#F6F7F5; --tinta:#1A2027;
    --redup:#6B7480; --garis:#E2E6E3; --merah:#A32B20; --merah-bg:#FBE9E7;
    --biru:#1D4ED8; --biru-bg:#EFF6FF;
  }
  *{box-sizing:border-box;}
  body{margin:0; background:var(--kertas); color:var(--tinta);
       font-family:"Inter","Segoe UI",system-ui,-apple-system,Arial,sans-serif;
       font-size:15px; line-height:1.55;}
  .bungkus{max-width:1150px; margin:0 auto; padding:30px 20px 70px;}
  a.kembali{font-size:13px; color:var(--redup); text-decoration:none;}
  a.kembali:hover{color:var(--hijau);}
  h1{font-size:25px; margin:8px 0 4px; letter-spacing:-.01em;}
  .sub-judul{color:var(--redup); margin:0 0 24px;}

  .room-card{margin-bottom:32px;}
  .room-header{display:flex; align-items:center; justify-content:space-between; margin-bottom:10px; padding:0 4px;}
  .room-title{font-size:18px; font-weight:700; color:var(--tinta); margin:0;}
  .room-code-badge{background:#E2E8F0; color:#334155; padding:2px 8px; border-radius:4px; font-size:13px; font-weight:600; margin-left:8px;}

  .panel{background:#fff; border:1px solid var(--garis); border-radius:10px; overflow:hidden;}
  table{width:100%; border-collapse:collapse;}
  th,td{padding:12px 14px; text-align:left; vertical-align:middle; border-bottom:1px solid var(--garis);}
  th{font-size:12px; color:var(--redup); font-weight:600; background:#FBFCFB;}
  tbody tr:last-child td{border-bottom:none;}
  
  .nama{font-weight:600;}
  .sub{font-size:12.5px; color:var(--redup);}
  
  .badge{display:inline-block; padding:3px 10px; border-radius:20px; font-size:12px; font-weight:600; white-space:nowrap;}
  .status-selesai{background:#DCFCE7; color:#15803D;}
  .status-gugur{background:var(--merah-bg); color:var(--merah);}
  .status-proses{background:var(--biru-bg); color:var(--biru);}

  .nilai-text{font-weight:700; font-size:15px; color:#0f172a;}
  .nilai-kosong{color:var(--redup); font-weight:normal; font-style:italic; font-size:13px;}

  .log-count-badge{display:inline-block; padding:4px 12px; border-radius:6px; font-size:13px; font-weight:600; background:#F1F5F9; color:#475569; border:1px solid var(--garis);}
  .log-count-badge.has-violation{background:var(--merah-bg); color:var(--merah); border-color:#F0C8C2;}

  .kosong{padding:56px 24px; text-align:center; color:var(--redup); background:#fff; border:1px solid var(--garis); border-radius:10px;}
  .kosong strong{display:block; color:var(--tinta); font-size:16px; margin-bottom:6px;}
  .galat{background:var(--merah-bg); color:var(--merah); border:1px solid #F0C8C2; border-radius:10px; padding:14px 18px; margin-bottom:20px; font-size:14px;}
</style>
</head>
<body>
<div class="bungkus">

  <a class="kembali" href="dashboard.php">&larr; Kembali ke dashboard</a>
  <h1>Log Ujian & Nilai Siswa</h1>
  <p class="sub-judul">
    <?= $selectedRoomId ? 'Menampilkan ringkasan aktivitas siswa untuk ruangan terpilih.' : 'Melihat daftar siswa dan ringkasan pelanggaran untuk semua ruang ujian.' ?>
  </p>

  <?php if ($error): ?>
    <div class="galat">Data gagal dimuat: <?= esc($error) ?></div>
  <?php endif; ?>

  <?php if (empty($roomsGrouped)): ?>
    <div class="kosong">
      <strong>Belum ada data siswa ditemukan</strong>
      <?= $selectedRoomId ? 'Ruang ujian ini belum memiliki aktivitas siswa.' : 'Belum ada siswa yang bergabung di ruang ujian mana pun.' ?>
    </div>
  <?php else: ?>
    <?php foreach ($roomsGrouped as $roomId => $room): ?>
      <div class="room-card">
        <div class="room-header">
          <h2 class="room-title">
            <?= esc($room['subject_name']) ?> 
            <span class="room-code-badge">Kode: <?= esc($room['room_code']) ?></span>
          </h2>
          <span class="sub"><?= count($room['students']) ?> Siswa</span>
        </div>

        <div class="panel">
          <table>
            <thead>
              <tr>
                <th>Siswa</th>
                <th>Status</th>
                <th>Nilai</th>
                <th style="text-align:right;">Total Pelanggaran</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($room['students'] as $s): ?>
              <?php 
                $vCount   = (int)($s['violation_count'] ?? 0);
                $scoreVal = $s['score'] !== null ? $s['score'] : null;
                $isForfeited = $s['session_status'] === 'forfeited';
              ?>
              <tr>
                <td>
                  <div class="nama"><?= esc($s['student_name']) ?></div>
                  <div class="sub"><?= esc($s['identity_number']) ?></div>
                </td>
                <td><?= status_badge($s['session_status']) ?></td>
                <td>
                  <?php if ($scoreVal !== null): ?>
                    <span class="nilai-text"><?= esc($scoreVal) ?></span>
                  <?php else: ?>
                    <span class="nilai-kosong">Belum ada nilai</span>
                  <?php endif; ?>
                </td>
                <td style="text-align:right;">
                  <?php if ($isForfeited): ?>
                    <span class="sub" style="color:var(--merah); font-weight:600;">Dikeluarkan</span>
                  <?php else: ?>
                    <span class="log-count-badge <?= $vCount > 0 ? 'has-violation' : '' ?>">
                      <?= $vCount ?> Pelanggaran
                    </span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>

</div>
</body>
</html>