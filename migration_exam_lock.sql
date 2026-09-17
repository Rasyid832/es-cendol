-- migration_add_question_text.sql
-- Menambahkan kolom question_text ke tabel rooms (naskah soal ujian),
-- yang dibutuhkan oleh views/lecturer/create_room.php tapi belum ada di database.
--
-- Aman dijalankan berkali-kali. Jalankan lewat:
--   mysql -u root -p codeprocess_db < migration_add_question_text.sql
-- atau phpMyAdmin: pilih database -> tab SQL -> paste -> Kirim/Go

USE codeprocess_db;

SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rooms' AND COLUMN_NAME = 'question_text'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE rooms ADD COLUMN question_text LONGTEXT DEFAULT NULL AFTER description',
    'SELECT "Kolom rooms.question_text sudah ada, dilewati." AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT 'Migrasi question_text selesai.' AS status;