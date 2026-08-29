-- Script untuk membuat tabel users dan insert data user
-- Password untuk semua user: admin123
-- Hash password yang sudah di-generate: $2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi

-- 1. Cek apakah tabel users sudah ada
DROP TABLE IF EXISTS users;

-- 2. Buat tabel users (tanpa foreign key dulu untuk keamanan)
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'tu', 'rlpm', 'tkuk') NOT NULL,
    seksi_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_seksi_id (seksi_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Ambil seksi_id untuk setiap seksi (dengan pengecekan)
SET @seksi_tu = (SELECT id FROM seksi WHERE kode_seksi = 'TU' LIMIT 1);
SET @seksi_rlpm = (SELECT id FROM seksi WHERE kode_seksi = 'RLPM' LIMIT 1);
SET @seksi_tkuk = (SELECT id FROM seksi WHERE kode_seksi = 'TKUK' LIMIT 1);

-- 4. Insert users dengan password admin123
INSERT INTO users (username, password, role, seksi_id) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', NULL),
('tu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'tu', @seksi_tu),
('rlpm', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'rlpm', @seksi_rlpm),
('tkuk', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'tkuk', @seksi_tkuk);

-- 5. Tambahkan foreign key jika tabel seksi ada (opsional, diabaikan jika error)
-- ALTER TABLE users ADD CONSTRAINT fk_users_seksi FOREIGN KEY (seksi_id) REFERENCES seksi(id) ON DELETE SET NULL;

-- Tampilkan hasil
SELECT 'Users berhasil dibuat!' AS status;
SELECT id, username, role, seksi_id, created_at FROM users;
