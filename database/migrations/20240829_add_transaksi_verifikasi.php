<?php

declare(strict_types=1);

/**
 * Migration: Input Transaksi Mandiri per Seksi + Verifikasi
 * - Tambah kolom status/verifikasi di tabel transaksi (tanpa mengubah kolom lama)
 * - Buat tabel audit_log jika belum ada
 * - Seed akun role seksi (RLPM, TKUK) jika belum ada
 *
 * Catatan: $pdo tersedia dari migrate.php runner.
 */

function migration_column_exists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?');
    $stmt->execute([$table, $column]);
    return (int) $stmt->fetchColumn() > 0;
}

function migration_table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
    $stmt->execute([$table]);
    return (int) $stmt->fetchColumn() > 0;
}

// ============ 1. Kolom verifikasi di tabel transaksi ============
if (!migration_column_exists($pdo, 'transaksi', 'status')) {
    $pdo->exec("ALTER TABLE transaksi ADD COLUMN status ENUM('diajukan','diverifikasi','ditolak') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'diverifikasi' AFTER nomor_bukti");
}
if (!migration_column_exists($pdo, 'transaksi', 'input_by')) {
    $pdo->exec('ALTER TABLE transaksi ADD COLUMN input_by INT NULL DEFAULT NULL AFTER status');
}
if (!migration_column_exists($pdo, 'transaksi', 'diverifikasi_by')) {
    $pdo->exec('ALTER TABLE transaksi ADD COLUMN diverifikasi_by INT NULL DEFAULT NULL AFTER input_by');
}
if (!migration_column_exists($pdo, 'transaksi', 'diverifikasi_at')) {
    $pdo->exec('ALTER TABLE transaksi ADD COLUMN diverifikasi_at TIMESTAMP NULL DEFAULT NULL AFTER diverifikasi_by');
}
if (!migration_column_exists($pdo, 'transaksi', 'catatan_verifikasi')) {
    $pdo->exec('ALTER TABLE transaksi ADD COLUMN catatan_verifikasi VARCHAR(255) NULL DEFAULT NULL AFTER diverifikasi_at');
}

// ============ 2. Tabel audit_log ============
if (!migration_table_exists($pdo, 'audit_log')) {
    $pdo->exec("
        CREATE TABLE audit_log (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL DEFAULT NULL,
            aksi VARCHAR(100) NOT NULL,
            tabel VARCHAR(100) NULL DEFAULT NULL,
            record_id INT NULL DEFAULT NULL,
            keterangan TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_audit_aksi (aksi),
            INDEX idx_audit_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

// ============ 3. Seed akun role seksi (TU, RLPM, TKUK) ============
// Role codebase saat ini: admin (bendahara/verifikator), tu, rlpm, tkuk.
// TU/RLPM/TKUK = pen input transaksi seksi. Pastikan ada akun utk masing-masing.
if (migration_table_exists($pdo, 'users') && migration_table_exists($pdo, 'seksi')) {
    $hash = null;
    $stmt = $pdo->query("SELECT password FROM users WHERE username = 'admin' LIMIT 1");
    $adminRow = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($adminRow) {
        $hash = $adminRow['password'];
    }

    $seksiList = [
        'tu'   => 'TU',
        'rlpm' => 'RLPM',
        'tkuk' => 'TKUK',
    ];

    foreach ($seksiList as $username => $kodeSeksi) {
        // Cek akun sudah ada
        $existing = $pdo->prepare('SELECT id, seksi_id FROM users WHERE username = ?');
        $existing->execute([$username]);
        $row = $existing->fetch(PDO::FETCH_ASSOC);

        // Cari seksi_id
        $seksiStmt = $pdo->prepare('SELECT id FROM seksi WHERE kode_seksi = ? LIMIT 1');
        $seksiStmt->execute([$kodeSeksi]);
        $seksiId = $seksiStmt->fetchColumn();

        if ($row) {
            // Pastikan role dan seksi_id terisi
            $pdo->prepare('UPDATE users SET role = ?, seksi_id = ? WHERE id = ?')
                ->execute([$username, $seksiId ?: null, $row['id']]);
        } else {
            $passwordHash = $hash ?: password_hash('admin123', PASSWORD_DEFAULT);
            $pdo->prepare('INSERT INTO users (username, password, role, seksi_id) VALUES (?, ?, ?, ?)')
                ->execute([$username, $passwordHash, $username, $seksiId ?: null]);
        }
    }
}
