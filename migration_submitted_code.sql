-- migration_submitted_code.sql
-- Menambahkan kolom untuk menyimpan kode yang dikumpulkan siswa,
-- supaya dosen bisa melihat & menilai hasil kerja di dashboard.
--
-- Jalankan lewat phpMyAdmin: pilih codeprocess_db -> tab SQL -> paste -> Kirim

USE codeprocess_db;

SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sessions' AND COLUMN_NAME = 'submitted_code'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE sessions ADD COLUMN submitted_code LONGTEXT DEFAULT NULL AFTER violation_count',
    'SELECT "Kolom sessions.submitted_code sudah ada, dilewati." AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists2 := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sessions' AND COLUMN_NAME = 'submitted_language'
);
SET @sql2 := IF(@col_exists2 = 0,
    'ALTER TABLE sessions ADD COLUMN submitted_language VARCHAR(50) DEFAULT NULL AFTER submitted_code',
    'SELECT "Kolom sessions.submitted_language sudah ada, dilewati." AS info'
);
PREPARE stmt2 FROM @sql2; EXECUTE stmt2; DEALLOCATE PREPARE stmt2;

SELECT 'Migrasi submitted_code selesai.' AS status;