-- migration_exam_lock.sql
-- Menambahkan dukungan status 'forfeited' (gugur) dan penghitung pelanggaran
-- pada tabel sessions, untuk fitur: siswa tidak boleh masuk lagi setelah
-- keluar/curang saat ujian berlangsung.
--
-- Aman dijalankan berkali-kali. Jalankan lewat:
--   mysql -u root -p codeprocess_db < migration_exam_lock.sql
-- atau phpMyAdmin: pilih database -> tab SQL -> paste -> Kirim/Go

USE codeprocess_db;

-- Perluas ENUM status agar mencakup 'forfeited'
ALTER TABLE sessions
    MODIFY COLUMN status ENUM('ongoing', 'completed', 'forfeited') DEFAULT 'ongoing';

-- Tambah kolom violation_count jika belum ada
SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sessions' AND COLUMN_NAME = 'violation_count'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE sessions ADD COLUMN violation_count INT NOT NULL DEFAULT 0 AFTER score',
    'SELECT "Kolom sessions.violation_count sudah ada, dilewati." AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT 'Migrasi exam-lock selesai.' AS status;