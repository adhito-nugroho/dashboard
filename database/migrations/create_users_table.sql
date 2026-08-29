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

-- Password untuk semua user: admin123
-- Hash: $2y$10$YourHashHere (akan diganti setelah hash dihasilkan)

-- Query untuk mendapatkan seksi_id:
-- SELECT id FROM seksi WHERE kode_seksi = 'TU'; -- untuk user TU
-- SELECT id FROM seksi WHERE kode_seksi = 'RLPM'; -- untuk user RLPM
-- SELECT id FROM seksi WHERE kode_seksi = 'TKUK'; -- untuk user TKUK

-- Contoh insert (sesuaikan seksi_id dengan hasil query di atas):
-- INSERT INTO users (username, password, role, seksi_id) VALUES
-- ('admin', 'HASH_PASSWORD', 'admin', NULL),
-- ('tu', 'HASH_PASSWORD', 'tu', ID_SEKSI_TU),
-- ('rlpm', 'HASH_PASSWORD', 'rlpm', ID_SEKSI_RLPM),
-- ('tkuk', 'HASH_PASSWORD', 'tkuk', ID_SEKSI_TKUK);
