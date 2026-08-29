-- Script untuk membuat tabel users dan insert data user
-- Password untuk semua user: admin123
-- Hash password: $2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi

-- 1. Backup data lama jika ada
CREATE TABLE IF NOT EXISTS users_backup_old AS SELECT * FROM users;

-- 2. Disable foreign key checks sementara
SET FOREIGN_KEY_CHECKS = 0;

-- 3. Drop tabel users lama
DROP TABLE IF EXISTS users;

-- 4. Enable foreign key checks kembali
SET FOREIGN_KEY_CHECKS = 1;

-- 5. Buat tabel users baru
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'tu', 'rlpm', 'tkuk') NOT NULL,
    seksi_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_seksi_id (seksi_id),
    INDEX idx_username (username),
    INDEX idx_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Ambil seksi_id untuk setiap seksi
SET @seksi_tu = (SELECT id FROM seksi WHERE kode_seksi = 'TU' LIMIT 1);
SET @seksi_rlpm = (SELECT id FROM seksi WHERE kode_seksi = 'RLPM' LIMIT 1);
SET @seksi_tkuk = (SELECT id FROM seksi WHERE kode_seksi = 'TKUK' LIMIT 1);

-- 7. Insert users dengan password admin123
INSERT INTO users (username, password, role, seksi_id) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', NULL),
('tu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'tu', @seksi_tu),
('rlpm', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'rlpm', @seksi_rlpm),
('tkuk', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'tkuk', @seksi_tkuk);

-- 8. Tampilkan hasil
SELECT 'Users berhasil dibuat!' AS status;
SELECT id, username, role, seksi_id, created_at FROM users;

-- 9. Info: Data lama ada di tabel users_backup_old (jika ada)
SELECT 'Backup tabel lama ada di: users_backup_old' AS info;
