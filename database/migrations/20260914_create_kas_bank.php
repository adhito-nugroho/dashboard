<?php

declare(strict_types=1);

/**
 * Migration: Tabel kas_bank untuk mencatat mutasi Saldo Awal, Pencairan UP, Pencairan GU, dan Kas Masuk/Keluar.
 */

if (!isset($pdo) && class_exists('Database')) {
    $pdo = Database::getConnection();
}

$pdo->exec("
    CREATE TABLE IF NOT EXISTS `kas_bank` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `tanggal` DATE NOT NULL,
        `tahun` INT NOT NULL,
        `bulan` INT NOT NULL,
        `jenis` ENUM('saldo_awal', 'up', 'gu', 'setoran', 'lainnya') NOT NULL DEFAULT 'gu',
        `nomor_bukti` VARCHAR(100) NULL,
        `keterangan` VARCHAR(255) NOT NULL,
        `nominal` DECIMAL(15, 2) NOT NULL DEFAULT 0,
        `created_by` INT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_kas_periode` (`tahun`, `bulan`),
        INDEX `idx_kas_tanggal` (`tanggal`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

// Seeding saldo awal September 2026 (Rp 4.734.506) jika belum ada
$checkStmt = $pdo->prepare("SELECT COUNT(*) FROM `kas_bank` WHERE `tahun` = 2026 AND `bulan` = 9 AND `jenis` = 'saldo_awal'");
$checkStmt->execute();
if ((int) $checkStmt->fetchColumn() === 0) {
    $insertStmt = $pdo->prepare("
        INSERT INTO `kas_bank` (`tanggal`, `tahun`, `bulan`, `jenis`, `nomor_bukti`, `keterangan`, `nominal`, `created_by`)
        VALUES ('2026-09-01', 2026, 9, 'saldo_awal', 'SALDO-AWAL-SEP-2026', 'Saldo Awal Kas/Bank Bulan September 2026', 4734506.00, 1)
    ");
    $insertStmt->execute();
}
