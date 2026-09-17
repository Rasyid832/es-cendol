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

$sql = "SELECT l.id, l.log_type, l.description, l.created_at,
               u.name AS student_name, u.identity_number,
               r.room_code, r.subject_name
        FROM logs l
        JOIN sessions s ON s.id = l.session_id
        JOIN users    u ON u.id = s.student_id
        JOIN rooms    r ON r.id = s.room_id";
$params = [];
if ($userRole !== 'admin') { $sql .= " WHERE r.lecturer_id = ?"; $params[] = $userId; }
$sql .= " ORDER BY l.created_at DESC, l.id DESC";

$error = null; $logs = [];
try { $logs = db_all($sql, $params); } catch (Throwable $e) { $error = $e->getMessage(); }

function esc($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }

function log_label($t) {
    $map = [
        'tab_switch'      => 'Pindah tab',
        'window_blur'     => 'Jendela ditinggalkan',
        'blur'            => 'Jendela ditinggalkan',
        'fullscreen_exit' => 'Keluar layar penuh',
        'copy_paste'      => 'Salin–tempel',
        'paste'           => 'Tempel dari luar',
        'copy'            => 'Menyalin soal',
        'right_click'     => 'Klik kanan',
        'devtools_open'   => 'Membuka DevTools',
        'typing_anomaly'  => 'Pola ketik janggal',
        'screenshot'      => 'Tangkapan layar',
        'rejoin'          => 'Masuk ulang ke ujian',
    ];
    $k = strtolower(trim((string) $t));
    return $map[$k] ?? ucfirst(str_replace('_', ' ', $k));
}

function waktu_id($ts) {
    if (!$ts) return '—';
    $t = strtotime($ts);
    $b = ['', 'Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
    return date('d', $t) . ' ' . $b[(int) date('n', $t)] . ' ' . date('Y H:i', $t);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Log Pelanggaran Siswa</title>
<style>
  :root{
    --hijau:#1F6F4A; --kertas:#F6F7F5; --tinta:#1A2027;
    --redup:#6B7480; --garis:#E2E6E3; --merah:#A32B20; --merah-bg:#FBE9E7;
  }
  *{box-sizing:border-box;}
  body{margin:0; background:var(--kertas); color:var(--tinta);
       font-family:"Inter","Segoe UI",system-ui,-apple-system,Arial,sans-serif;
       font-size:15px; line-height:1.55;}
  .bungkus{max-width:1000px; margin:0 auto; padding:30px 20px 70px;}
  a.kembali{font-size:13px; color:var(--redup); text-decoration:none;}
  a.kembali:hover{color:var(--hijau);}
  h1{font-size:25px; margin:8px 0 4px; letter-spacing:-.01em;}
  .sub-judul{color:var(--redup); margin:0 0 24px;}

  .panel{background:#fff; border:1px solid var(--garis); border-radius:10px; overflow:hidden;}
  table{width:100%; border-collapse:collapse;}
  th,td{padding:12px 14px; text-align:left; vertical-align:top; border-bottom:1px solid var(--garis);}
  th{font-size:12px; color:var(--redup); font-weight:600; background:#FBFCFB;}
  tbody tr:last-child td{border-bottom:none;}
  td.waktu{white-space:nowrap; font-variant-numeric:tabular-nums; color:var(--redup); font-size:13px;}
  .nama{font-weight:600;}
  .sub{font-size:12.5px; color:var(--redup);}
  .jenis{display:inline-block; background:var(--merah-bg); color:var(--merah);
         padding:2px 9px; border-radius:20px; font-size:12px; font-weight:600; white-space:nowrap;}

  .kosong{padding:56px 24px; text-align:center; color:var(--redup);}
  .kosong strong{display:block; color:var(--tinta); font-size:16px; margin-bottom:6px;}
  .galat{background:var(--merah-bg); color:var(--merah); border:1px solid #F0C8C2;
         border-radius:10px; padding:14px 18px; margin-bottom:20px; font-size:14px;}

  @media (max-width:760px){ .sembunyi-hp{display:none;} }
</style>
</head>
<body>
<div class="bungkus">

  <a class="kembali" href="dashboard.php">&larr; Kembali ke dashboard</a>
  <h1>Log pelanggaran siswa</h1>
  <p class="sub-judul">Catatan indikasi kecurangan yang terekam sistem selama ujian berlangsung.</p>

  <?php if ($error): ?>
    <div class="galat">Data gagal dimuat: <?= esc($error) ?></div>
  <?php endif; ?>

  <div class="panel">
    <?php if (!$logs): ?>
      <div class="kosong">
        <strong>Belum ada pelanggaran tercatat</strong>
        Log akan muncul di sini begitu sistem mendeteksi kecurangan saat ujian.
      </div>
    <?php else: ?>
      <table>
        <thead>
          <tr>
            <th style="width:150px;">Waktu</th>
            <th>Siswa</th>
            <th class="sembunyi-hp">Ruang ujian</th>
            <th>Jenis pelanggaran</th>
            <th class="sembunyi-hp">Keterangan</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($logs as $l): ?>
          <tr>
            <td class="waktu"><?= esc(waktu_id($l['created_at'])) ?></td>
            <td>
              <div class="nama"><?= esc($l['student_name']) ?></div>
              <div class="sub"><?= esc($l['identity_number']) ?></div>
            </td>
            <td class="sembunyi-hp">
              <div><?= esc($l['room_code']) ?></div>
              <div class="sub"><?= esc($l['subject_name']) ?></div>
            </td>
            <td><span class="jenis"><?= esc(log_label($l['log_type'])) ?></span></td>
            <td class="sembunyi-hp sub"><?= $l['description'] !== null && $l['description'] !== '' ? esc($l['description']) : '—' ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

</div>
</body>
</html>