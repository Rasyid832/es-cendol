<?php
session_start();

$nama_guru = $_SESSION['nama_guru'] ?? 'Nama Guru';
$role = 'Pengajar / Instructor';
$inisial = strtoupper(substr($nama_guru, 0, 2));
// Nanti proses submit form di sini
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $room_name   = $_POST['room_name'] ?? '';
    $password    = $_POST['password'] ?? '';
    $timer       = $_POST['timer'] ?? '';
    $description = $_POST['description'] ?? '';

    // TODO: validasi + simpan ke database
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Buat Room - ExamBro</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-[#0a0e1a] text-white font-sans  ">
<header class="flex justify-between items-center bg-[#0d1b3f] border-b border-[#1e3a6d] px-8 py-3.5">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-full bg-[#3b5fa8] flex items-center justify-center font-bold text-sm">
                <?= htmlspecialchars($inisial) ?>
            </div>
            <div class="flex flex-col">
                <span class="font-semibold text-[15px]"><?= htmlspecialchars($nama_guru) ?></span>
                <span class="text-xs text-[#a7b8d8]"><?= htmlspecialchars($role) ?></span>
            </div>
        </div>
    </header>
    <main class="flex flex-col items-center">
        <h1 class="text-center text-[2rem] font-bold text-[#4d7ae0] tracking-wide mb-10 mt-10">
            CREATE NEW ROOM
        </h1>
        <div class="bg-[#0d1b3f] border border-[#1e3a6d] rounded-2xl w-full max-w-[65%] px-9 py-12 flex flex-col ">
            <form method="POST" action="buat_room.php" class="flex flex-row item-center gap-20 ">
                <div class="flex flex-col ">
                    <label for="Nama Room" class="text-[2rem]">Nama Room</label>
                    <input type="text" class="py-[7px] px-[180px] bg-[#5c72a0]">
                    <label for="PASSWORD" class="text-[2rem] ">Password</label>
                    <input type="text"  class="py-[7px] bg-[#5c72a0] ">
                    <label for="Waktu" class="text-[2rem]">Waktu</label>
                    <input type="time"  class="py-[7px] bg-[#5c72a0]">
                    <label for="Peserta" class="text-[2rem]">Peserta</label>
                    <input type="number"  class="py-[7px] bg-[#5c72a0]">
                </div>
                <div class="flex flex-col   ">
                    <label for="Deskripsi Soal" class="text-[2rem]">Deskripsi Soal</label>
                    <input type="text" class="py-[136.5px] px-[190px] bg-[#5c72a0]" > 
                </div>
                <button type="submit"
                    class="absolute left-[47%] top-[62%]  bg-[#4d7ae0] hover:bg-[#3f68c9] text-white font-semibold
                        px-6 py-3 rounded-full self-center">
                    Submit
                </button>
            </form>
        </div>
    </main>
</body>
</html>