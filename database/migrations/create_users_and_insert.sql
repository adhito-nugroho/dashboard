-- Script untuk membuat tabel users dan insert data user
-- Password untuk semua user: admin123
-- Hash password yang sudah di-generate: $2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi

-- 1. Buat tabel users
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'tu', 'rlpm', 'tkuk') NOT NULL,
    seksi_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (seksi_id) REFERENCES seksi(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Ambil seksi_id untuk setiap seksi
SET @seksi_tu = (SELECT id FROM seksi WHERE kode_seksi = 'TU' LIMIT 1);
SET @seksi_rlpm = (SELECT id FROM seksi WHERE kode_seksi = 'RLPM' LIMIT 1);
SET @seksi_tkuk = (SELECT id FROM seksi WHERE kode_seksi = 'TKUK' LIMIT 1);

-- 3. Insert users dengan password admin123
INSERT INTO users (username, password, role, seksi_id) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', NULL),
('tu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'tu', @seksi_tu),
('rlpm', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'rlpm', @seksi_rlpm),
('tkuk', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'tkuk', @seksi_tkuk);

-- Tampilkan hasil
SELECT 'Users berhasil dibuat:' AS status;
SELECT id, username, role, seksi_id FROM users;
