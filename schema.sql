-- Buat database jika belum ada
CREATE DATABASE IF NOT EXISTS codeprocess_db;
USE codeprocess_db;

-- 1. TABEL USERS (Student, Lecturer, Admin)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,            -- Tambahan kolom Nama/Username
    email VARCHAR(255) NOT NULL UNIQUE,
    identity_number VARCHAR(100) NOT NULL, -- NIM atau NIG
    password VARCHAR(255) NOT NULL,       -- Hashed password
    role ENUM('student', 'lecturer', 'admin') DEFAULT 'student',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. TABEL EXAM_ROOMS (Dibuat oleh Lecturer)
CREATE TABLE IF NOT EXISTS exam_rooms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_code VARCHAR(10) NOT NULL UNIQUE,
    title VARCHAR(255) NOT NULL,          
    passcode VARCHAR(50) NOT NULL,         
    duration_minutes INT DEFAULT 60,      
    lecturer_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (lecturer_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 3. TABEL TELEMETRY_LOGS (Data Ketikan & Media Siswa)
CREATE TABLE IF NOT EXISTS telemetry_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    room_id INT NOT NULL,
    flight_time_data JSON,                 
    media_url VARCHAR(255),               
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (room_id) REFERENCES exam_rooms(id) ON DELETE CASCADE
);

-- DATA DUMMY AWAL
INSERT INTO users (name, email, identity_number, password, role) VALUES
('Administrator', 'admin@codeprocess.id', 'ADM001', '$2y$10$e.1s2yGf3s4d5f6g7h8i9o.0a1b2c3d4e5f6g7h8i9o', 'admin'),
('Dosen Pengampu', 'lecturer@univ.ac.id', 'NIG12345', '$2y$10$e.1s2yGf3s4d5f6g7h8i9o.0a1b2c3d4e5f6g7h8i9o', 'lecturer')
ON DUPLICATE KEY UPDATE id=id;