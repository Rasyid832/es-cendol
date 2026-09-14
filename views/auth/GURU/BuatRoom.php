<?php
session_start();

$nama_guru = $_SESSION['nama_guru'] ?? 'Nama Guru';
$role = 'Pengajar / Instructor';
$inisial = strtoupper(substr($nama_guru, 0, 2));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // TODO: proses & simpan data
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Buat Room - ExamBro</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-[#0a0e1a] text-white font-sans">

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

    <main class="flex flex-col items-center px-4">
        <h1 class="text-center text-[2rem] font-bold text-[#4d7ae0] tracking-wide mb-10 mt-10">
            CREATE NEW ROOM
        </h1>

        <div class="bg-[#0d1b3f] border border-[#1e3a6d] rounded-2xl w-full max-w-4xl px-9 py-12">
            <form method="POST" action="buat_room.php"
                  class="flex flex-col md:flex-row gap-10 md:gap-16">

                <!-- Kolom kiri: field-field -->
                <div class="flex flex-col gap-4 flex-1">
                    <div>
                        <label for="nama_room" class="text-lg block mb-1">Nama Room</label>
                        <input type="text" id="nama_room" name="nama_room" required
                            class="w-full py-2 px-3 rounded bg-[#5c72a0] text-white
                                   placeholder-[#e2e8f5] focus:outline-none focus:ring-2 focus:ring-[#4d7ae0]">
                    </div>

                    <div>
                        <label for="password" class="text-lg block mb-1">Password</label>
                        <input type="text" id="password" name="password" required
                            class="w-full py-2 px-3 rounded bg-[#5c72a0] text-white
                                   focus:outline-none focus:ring-2 focus:ring-[#4d7ae0]">
                    </div>

                    <div>
                        <label for="waktu" class="text-lg block mb-1">Waktu</label>
                        <input type="time" id="waktu" name="waktu"
                            class="w-full py-2 px-3 rounded bg-[#5c72a0] text-white
                                   focus:outline-none focus:ring-2 focus:ring-[#4d7ae0]">
                    </div>

                    <div>
                        <label for="peserta" class="text-lg block mb-1">Peserta</label>
                        <input type="number" id="peserta" name="peserta" min="1"
                            class="w-full py-2 px-3 rounded bg-[#5c72a0] text-white
                                   focus:outline-none focus:ring-2 focus:ring-[#4d7ae0]">
                    </div>
                </div>

                <!-- Kolom kanan: deskripsi -->
                <div class="flex flex-col flex-1">
                    <label for="deskripsi" class="text-lg block mb-1">Deskripsi Soal</label>
                    <textarea id="deskripsi" name="deskripsi" rows="8"
                        class="w-full h-full min-h-[220px] py-3 px-3 rounded bg-[#5c72a0] text-white
                               resize-none focus:outline-none focus:ring-2 focus:ring-[#4d7ae0]"></textarea>
                </div>

            </form>

            <div class="flex justify-center mt-8">
                <button type="submit" form="" onclick="document.forms[0].submit()"
                    class="bg-[#4d7ae0] hover:bg-[#3f68c9] text-white font-semibold
                           px-8 py-3 rounded-full">
                    Submit
                </button>
            </div>
        </div>
    </main>

</body>
</html>