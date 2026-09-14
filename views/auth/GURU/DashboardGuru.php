<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

if (($_SESSION['role'] ?? '') !== 'lecturer') {
    header("Location: ../login.php");
    exit();
}

$nama_guru = $_SESSION['name'] ?? 'Nama Guru';
$role = 'Pengajar / Instructor';
$inisial = strtoupper(substr($nama_guru, 0, 2));

$rooms = [
    [
        'id' => 'RM-8492',
        'pw' => 'EXAM2026',
        'tms' => 20,
        'submit' => 16
    ],
    [
        'id' => 'RM-3105',
        'pw' => 'KODE99',
        'tms' => 20,
        'submit' => 16
    ]
];
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Guru - ExamBro</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-[#0a0e1a] text-white font-sans">

    <header class="flex justify-between items-center bg-[#0d1b3f] border-b border-[#1e3a6d] px-8 py-3.5">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-full bg-[#3b5fa8] flex items-center justify-center font-bold text-sm">
                <?= htmlspecialchars($inisial) ?>
            </div>

            <div class="flex flex-col">
                <span class="font-semibold text-[15px]">
                    <?= htmlspecialchars($nama_guru) ?>
                </span>

                <span class="text-xs text-[#a7b8d8]">
                    <?= htmlspecialchars($role) ?>
                </span>
            </div>
        </div>
    </header>

    <main class="px-12 py-10">

        <div class="flex justify-between items-center mb-6">
            <h1 class="text-xl font-semibold text-[#d7e2ff]">
                Daftar Room Ujian
            </h1>

            <button
                onclick="location.href='BuatRoom.php'"
                class="bg-[#4d7ae0] hover:bg-[#3f68c9] text-white font-semibold px-5 py-2.5 rounded-full transition"
            >
                + Buat Room
            </button>
        </div>

        <div class="flex flex-col gap-4">

            <?php foreach ($rooms as $room): ?>

                <div class="flex justify-between items-center bg-[#0d1b3f] border border-[#1e3a6d] rounded-2xl px-7 py-5">

                    <div>
                        <div class="flex items-center gap-2.5 mb-1.5">
                            <span class="text-[#7d8db3] text-sm w-6">
                                ID
                            </span>

                            <span class="bg-[#142a56] px-2.5 py-1 rounded-md font-mono text-sm">
                                <?= htmlspecialchars($room['id']) ?>
                            </span>
                        </div>

                        <div class="flex items-center gap-2.5">
                            <span class="text-[#7d8db3] text-sm w-6">
                                PW
                            </span>

                            <span class="bg-[#142a56] px-2.5 py-1 rounded-md font-mono text-sm">
                                <?= htmlspecialchars($room['pw']) ?>
                            </span>
                        </div>
                    </div>

                    <div class="flex gap-8 bg-[#142a56] px-6 py-3 rounded-xl">

                        <div class="flex flex-col items-center">
                            <span class="text-[11px] text-[#8fa1c9] uppercase">
                                TMS (Total Siswa)
                            </span>

                            <span class="text-xl font-bold">
                                <?= (int)$room['tms'] ?>
                            </span>
                        </div>

                        <div class="flex flex-col items-center">
                            <span class="text-[11px] text-[#8fa1c9] uppercase">
                                Submit
                            </span>

                            <span class="text-xl font-bold text-[#2ecf7a]">
                                <?= (int)$room['submit'] ?>
                            </span>
                        </div>

                    </div>

                </div>

            <?php endforeach; ?>

        </div>

    </main>

</body>
</html>