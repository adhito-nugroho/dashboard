<?php

declare(strict_types=1);

/**
 * Migration: Menambahkan kolom status ('menunggu_cair', 'cair') dan tanggal_cair pada tabel kas_bank,
 * serta menambahkan catatan pengajuan GU SPJ Agustus (Rp 79.382.494) status 'menunggu_cair'.
 */

if (!isset($pdo) && class_exists('Database')) {
    $pdo = Database::getConnection();
}

function migration_col_exists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?');
    $stmt->execute([$table, $column]);
    return (int) $stmt->fetchColumn() > 0;
}

if (!migration_col_exists($pdo, 'kas_bank', 'status')) {
    $pdo->exec("ALTER TABLE `kas_bank` ADD COLUMN `status` ENUM('menunggu_cair', 'cair') NOT NULL DEFAULT 'cair' AFTER `jenis`");
}

if (!migration_col_exists($pdo, 'kas_bank', 'tanggal_cair')) {
    $pdo->exec("ALTER TABLE `kas_bank` ADD COLUMN `tanggal_cair` DATE NULL DEFAULT NULL AFTER `tanggal`");
}

// Tambahkan record GU Agustus (Rp 79.382.494) status 'menunggu_cair' jika belum ada
$checkGu = $pdo->prepare("SELECT COUNT(*) FROM `kas_bank` WHERE `tahun` = 2026 AND `bulan` = 9 AND `nomor_bukti` = 'SPJ-GU/AGUSTUS/2026'");
$checkGu->execute();
if ((int) $checkGu->fetchColumn() === 0) {
    $insertGu = $pdo->prepare("
        INSERT INTO `kas_bank` (`tanggal`, `tanggal_cair`, `tahun`, `bulan`, `jenis`, `status`, `nomor_bukti`, `keterangan`, `nominal`, `created_by`)
        VALUES ('2026-09-01', NULL, 2026, 9, 'gu', 'menunggu_cair', 'SPJ-GU/AGUSTUS/2026', 'Pengajuan GU SPJ Bulan Agustus 2026 (Menunggu SP2D Cair Minggu ke-2)', 79382494.00, 1)
    ");
    $insertGu->execute();
}
