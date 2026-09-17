-- Buat database jika belum ada
CREATE DATABASE IF NOT EXISTS codeprocess_db;
USE codeprocess_db;

-- 1. TABEL USERS (Student, Lecturer, Admin)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    identity_number VARCHAR(100) NOT NULL, -- NIM / NIP
    password VARCHAR(255) NOT NULL,       -- Hashed password
    role ENUM('student', 'lecturer', 'admin') DEFAULT 'student',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. TABEL ROOMS (Dibuat oleh Lecturer)
CREATE TABLE IF NOT EXISTS rooms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lecturer_id INT NOT NULL,
    subject_name VARCHAR(255) NOT NULL,    -- Nama Ujian / Mata Kuliah
    class_name VARCHAR(100) DEFAULT NULL,   -- Kelas (misal: IF-45-01)
    exam_type VARCHAR(50) DEFAULT NULL,     -- Jenis Ujian (UAS, UTS, Kuis Mingguan, Praktikum, dst)
    room_code VARCHAR(50) NOT NULL UNIQUE,  -- Room ID (misal: CS101A)
    passcode VARCHAR(100) NOT NULL,         -- Kata Sandi Masuk
    duration INT NOT NULL DEFAULT 60,  -- Durasi Ujian (menit)
    start_time DATETIME NULL,  
    description TEXT DEFAULT NULL,          -- Deskripsi / Catatan Tambahan
    status ENUM('active', 'inactive', 'archived') DEFAULT 'active', -- Status Room (Aktif, Nonaktif, Arsip)
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (lecturer_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. TABEL SESSIONS (Siswa yang masuk/aktif di dalam room)
CREATE TABLE IF NOT EXISTS sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_id INT NOT NULL,
    student_id INT NOT NULL,
    status ENUM('ongoing', 'completed', 'forfeited') DEFAULT 'ongoing', -- forfeited = gugur krn keluar/curang
    score DECIMAL(5,2) DEFAULT NULL,        -- Nilai akhir ujian siswa (diisi setelah selesai dinilai)
    violation_count INT NOT NULL DEFAULT 0, -- Jumlah pelanggaran (pindah tab, keluar fullscreen, dst)
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    finished_at TIMESTAMP NULL DEFAULT NULL, -- Waktu siswa menyelesaikan/gugur dari ujian
    FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. TABEL LOGS (Catatan Temuan Pelanggaran / Indikasi Problem)
CREATE TABLE IF NOT EXISTS logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    log_type VARCHAR(50) NOT NULL,          -- Jenis pelanggaran (misal: tab_switch, copy_paste, typing_anomaly)
    description TEXT DEFAULT NULL,          -- Rincian bukti / temuan
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES sessions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. TABEL TELEMETRY_LOGS (Data Analisis Ketikan & Media Screenshot/Webcam)
CREATE TABLE IF NOT EXISTS telemetry_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    flight_time_data JSON DEFAULT NULL,     -- Data interval waktu ketikan keyboard
    media_url VARCHAR(255) DEFAULT NULL,    -- Link/Path foto bukti dari webcam/screen
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES sessions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ===================================================
-- DATA DUMMY INITIAL SETUP
-- ===================================================

-- Akun Default (Password default: admin123)
INSERT INTO users (name, email, identity_number, password, role) VALUES
('Administrator', 'admin@codeprocess.id', 'ADM001', '$2y$10$e.1s2yGf3s4d5f6g7h8i9o.0a1b2c3d4e5f6g7h8i9o', 'admin'),
('Dosen Pengampu', 'lecturer@univ.ac.id', '198501012010121001', '$2y$10$e.1s2yGf3s4d5f6g7h8i9o.0a1b2c3d4e5f6g7h8i9o', 'lecturer')
ON DUPLICATE KEY UPDATE id=id;

-- ===================================================
-- MIGRASI UNTUK DATABASE YANG SUDAH ADA SEBELUMNYA
-- (Jalankan blok ini jika tabel rooms/sessions sudah pernah dibuat
--  dari versi schema.sql yang lama, agar tidak perlu drop database)
-- ===================================================
-- ALTER TABLE rooms ADD COLUMN exam_type VARCHAR(50) DEFAULT NULL AFTER class_name;
-- ALTER TABLE sessions ADD COLUMN status ENUM('ongoing','completed') DEFAULT 'ongoing' AFTER student_id;
-- ALTER TABLE sessions ADD COLUMN score DECIMAL(5,2) DEFAULT NULL AFTER status;
-- ALTER TABLE sessions ADD COLUMN finished_at TIMESTAMP NULL DEFAULT NULL AFTER joined_at;