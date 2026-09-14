<?php
$nama_guru = $_SESSION['nama_guru'] ?? 'Nama Guru';
$role = 'Pengajar / Instructor';
$inisial = strtoupper(substr($nama_guru, 0, 2));

$students = [
    ["name" => "Rizky Ramadhan"],
    ["name" => "Ayu Lestari"],
    ["name" => "Budi Santoso"],
    ["name" => "Dwi Cahyono"],
    ["name" => "Eka Putri Pertiwi"],
    ["name" => "Fajar Nugraha"],
    ["name" => "Gita Gutawa"],
    ["name" => "Hadi Wijaya"],
    ["name" => "Indah Permata"],
    ["name" => "Joko Susilo"],
    ["name" => "Kiki Amalia"],
    ["name" => "Lutfi Ardiansyah"],
    ["name" => "Maya Anggraini"],
    ["name" => "Naufal Pratama"],
    ["name" => "Olivia Zalianty"],
    ["name" => "Putri Amelia"],
    ["name" => "Raka Pratama"],
    ["name" => "Salsa Nabila"],
    ["name" => "Taufik Hidayat"],
    ["name" => "Vina Maharani"]
];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Students List</title>

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


    <main class="flex justify-center items-center flex-col pb-10">

        <h1 class="text-white pt-[60px] text-[2rem] mb-7 font-bold">
            STUDENTS LIST
        </h1>


        <div class="border border-[#1e3a6d] w-full max-w-[500px] bg-[#0d1b3f] p-4 rounded-xl">

            <div class="max-h-[400px] overflow-y-auto pr-2 space-y-3">

                <?php foreach ($students as $student): ?>

                    <!-- KOTAK SETIAP SISWA -->
                    <div class="bg-[#162a52] border border-[#3b5fa8] rounded-lg px-5 py-3
                                hover:bg-[#1c3564] transition">

                        <h1 class="text-[1.1rem] font-medium">
                            <?= htmlspecialchars($student['name']); ?>
                        </h1>

                    </div>

                <?php endforeach; ?>

            </div>

        </div>


        <button
            type="submit"
            class="mt-8 text-[1.4rem] bg-[#4d7ae0] hover:bg-[#3f68c9]
                   text-white font-semibold px-[45px] py-3 rounded-full transition">

            MULAI

        </button>

    </main>

</body>

</html>
```
