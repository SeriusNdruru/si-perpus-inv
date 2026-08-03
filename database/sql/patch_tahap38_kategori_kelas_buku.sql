-- Tahap 38: kategori kelas pada katalog buku
-- Jalankan satu kali melalui phpMyAdmin sebelum kode Tahap 38 dideploy.

ALTER TABLE book_details
    ADD COLUMN IF NOT EXISTS grade_level VARCHAR(20) NOT NULL DEFAULT 'umum' AFTER publication_year;

UPDATE book_details
SET grade_level = 'umum'
WHERE grade_level IS NULL
   OR grade_level NOT IN ('umum', 'kelas_1', 'kelas_2', 'kelas_3', 'kelas_4', 'kelas_5', 'kelas_6');

ALTER TABLE book_details
    ADD INDEX IF NOT EXISTS idx_book_details_grade_level (grade_level);
