# es-cendol
Platform ujian pemrograman online berbasis web. Dosen membuat ruang ujian (room) lengkap dengan soal dan batas waktu; mahasiswa bergabung, mengerjakan soal langsung di code editor bawaan, dan hasilnya bisa dinilai oleh dosen. Dilengkapi sistem anti-kecurangan (deteksi pindah tab, mode layar penuh wajib, kamera aktif dengan deteksi HP/perangkat lain).

Dibangun dengan PHP native (tanpa framework) + PDO/MySQLi + MySQL, tanpa dependency eksternal di sisi server (library JS seperti TensorFlow.js dimuat dari CDN).

Kredensial Demo

Akun berikut sudah otomatis dibuat saat schema.sql diimport (lihat bagian Langkah Instalasi). Password di bawah ini asli dan berfungsi (di-hash pakai bcrypt yang valid, bukan data contoh/placeholder).

Peran	    Email (untuk login)	    Password	Keterangan
Dosen	    lecturer@univ.ac.id	    Dosen123!	Bisa langsung membuat room ujian & menilai hasil kerja mahasiswa
Mahasiswa	student@univ.ac.id	    Siswa123!	Bisa langsung join room ujian yang dibuat dosen

Ganti password akun-akun ini sebelum deploy ke server publik / production. Kredensial di atas hanya untuk demo/development lokal.

Kalau database kamu sudah pernah dibuat dari versi schema.sql yang lama (sebelum perbaikan ini), akun Admin & Dosen di atas tidak akan bisa login karena hash password-nya rusak. Jalankan migration_fix_demo_accounts.sql sekali lewat phpMyAdmin untuk memperbaikinya (lihat Troubleshooting).

Selain akun demo di atas, siapa pun juga bisa mendaftar akun baru sendiri lewat halaman Register (peran Mahasiswa atau Dosen).

Persyaratan Sistem
PHP 8.0+ dengan ekstensi pdo_mysql dan mysqli aktif
MySQL / MariaDB 5.7+
Web server: Apache (disarankan lewat XAMPP untuk development lokal) atau Nginx
Browser modern (Chrome/Edge/Firefox terbaru) — dibutuhkan untuk API kamera, fullscreen, dan deteksi objek berbasis browser
Koneksi internet aktif saat ujian berlangsung (library deteksi objek TensorFlow.js dimuat dari CDN, bukan disimpan lokal)

Langkah Instalasi
Clone repository
bash
   git clone https://github.com/Rasyid832/es-cendol.git
   cd es-cendol
Taruh project di folder web server Kalau pakai XAMPP di Windows, pindahkan/clone langsung ke C:\xampp\htdocs\es-cendol.
Buat file environment
bash
   cp .env.example .env

Lalu sesuaikan isinya dengan kredensial database kamu (lihat Konfigurasi).

Import struktur database Buat database baru bernama codeprocess_db lewat phpMyAdmin, lalu import schema.sql:
bash
   mysql -u root -p codeprocess_db < schema.sql

Atau lewat phpMyAdmin: buat database codeprocess_db → tab Impor → pilih file schema.sql → Kirim. Ini otomatis membuat semua tabel sekaligus 3 akun demo di atas.

Jalankan Apache & MySQL (lewat XAMPP Control Panel, atau sudo service apache2 start dan sudo service mysql start di Linux).
Buka di browser
   http://localhost/es-cendol/views/auth/login.php

Login pakai salah satu akun demo di atas.

Instalasi di database yang sudah pernah dipakai sebelumnya

Kalau kamu meng-update dari versi project yang lebih lama dan database-nya sudah ada isinya (jangan drop/reimport, nanti data hilang), jalankan migration satu per satu sesuai urutan berikut lewat phpMyAdmin (tab SQL → paste isi file → Kirim):

migration_exam_lock.sql — kolom status forfeited & penghitung pelanggaran
migration_add_question_text.sql — kolom naskah soal ujian
migration_submitted_code.sql — kolom penyimpan kode yang disubmit siswa

Konfigurasi (.env)

File .env (disalin dari .env.example) berisi parameter berikut:

Variabel	                Contoh Nilai	        Keterangan
DB_HOST	                    127.0.0.1	            Alamat server database
DB_NAME	                    codeprocess_db	        Nama database
DB_USER	root	            Username                MySQL
DB_PASS	(kosong)	        Password                MySQL — default XAMPP biasanya kosong
APP_ENV	                    development	            development = pesan error PHP ditampilkan 
                                                    detail ke browser (untuk debugging). production = error disembunyikan dari pengguna, dicatat ke log server saja. Wajib diganti ke production sebelum online publik.
SESSION_TIMEOUT_MINUTES	    30	                    Durasi tidak aktif sebelum pengguna otomatis logout

File .env tidak boleh ikut di-commit ke Git (sudah masuk .gitignore) karena berisi kredensial database.

Panduan Pengguna
Sebagai Dosen
Login pakai akun dosen (lecturer@univ.ac.id / Dosen123!, atau daftar akun dosen baru lewat halaman Register).
Buat Room Ujian → menu "Buat Room Ujian" di sidebar. Isi nama ujian, kelas, durasi (menit), password room, dan naskah soal (opsional — kalau dikosongkan, siswa akan lihat instruksi umum bawaan).
Bagikan Kode Room (mis. ROOM-CSP9) ke mahasiswa peserta ujian.
Setelah ujian berjalan/selesai, buka "Lihat hasil kerja siswa" (ikon mata di tabel Daftar Room Ujian) untuk melihat kode yang dikumpulkan tiap siswa, status pengerjaan (Sedang Mengerjakan/Selesai/Gugur), jumlah pelanggaran, dan memberi nilai.
Room yang sudah tidak dipakai bisa diarsipkan (History Ujian) atau dihapus permanen (ikon tong sampah, dengan konfirmasi).

Sebagai Mahasiswa
Login pakai akun mahasiswa (student@univ.ac.id / Siswa123!, atau daftar akun baru).
Di Dashboard, masukkan Kode Room dari dosen, klik Masuk.
Di halaman ujian, klik "Mulai Ujian (Fullscreen)" — ini akan meminta izin kamera dan mode layar penuh. Kedua izin ini wajib diberikan untuk mengikuti ujian.
Kerjakan soal di code editor (bisa pilih bahasa pemrograman), gunakan tombol Run Code untuk menguji, lalu Save & Submit untuk mengumpulkan.
Jangan pindah tab, keluar dari mode fullscreen, atau menutup halaman selama ujian — semua ini akan dicatat sebagai pelanggaran (lihat bagian keamanan di bawah).
