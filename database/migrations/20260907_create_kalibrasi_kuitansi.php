<?php

declare(strict_types=1);

/**
 * Migration: tabel kalibrasi_kuitansi (satu baris konfigurasi offset).
 * Template kuitansi hanya satu jenis (KWITANSI Kadishut Jatim, 215x165mm landscape).
 */

function migration_kalibrasi_table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
    $stmt->execute([$table]);
    return (int) $stmt->fetchColumn() > 0;
}

if (!migration_kalibrasi_table_exists($pdo, 'kalibrasi_kuitansi')) {
    $pdo->exec("
        CREATE TABLE `kalibrasi_kuitansi` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `offset_x_mm` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
            `offset_y_mm` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `updated_by` VARCHAR(100) NULL DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

// Seed satu baris default (offset 0,0) — hanya jika tabel masih kosong.
$count = (int) $pdo->query('SELECT COUNT(*) FROM `kalibrasi_kuitansi`')->fetchColumn();
if ($count === 0) {
    $pdo->exec("INSERT INTO `kalibrasi_kuitansi` (`offset_x_mm`, `offset_y_mm`, `updated_by`) VALUES (0.00, 0.00, 'system')");
}
