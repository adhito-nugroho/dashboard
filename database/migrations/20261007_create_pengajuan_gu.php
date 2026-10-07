<?php

declare(strict_types=1);

/**
 * Migration: Alur Pengajuan SPJ menjadi GU.
 * - Tabel pengajuan_gu: satu baris per pengajuan SPJ (batch transaksi yang
 *   dipilih admin untuk dimintakan Ganti Uang ke Kasda).
 * - Kolom transaksi.pengajuan_gu_id: penanda belanja sudah tercakup pengajuan
 *   (anti klaim ganda). NULL = belum di-SPJ-kan.
 */

if (!isset($pdo) && class_exists('Database')) {
    $pdo = Database::getConnection();
}

function migration_pg_col_exists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?');
    $stmt->execute([$table, $column]);
    return (int) $stmt->fetchColumn() > 0;
}

$pdo->exec("
    CREATE TABLE IF NOT EXISTS `pengajuan_gu` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `nomor_pengajuan` VARCHAR(100) NOT NULL UNIQUE,
        `tanggal_pengajuan` DATE NOT NULL,
        `keterangan` VARCHAR(255) NOT NULL DEFAULT '',
        `total_nominal` DECIMAL(15, 2) NOT NULL DEFAULT 0,
        `status` ENUM('diajukan', 'cair', 'ditolak') NOT NULL DEFAULT 'diajukan',
        `kas_bank_id` INT NULL DEFAULT NULL,
        `created_by` INT NULL DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_pengajuan_status` (`status`),
        INDEX `idx_pengajuan_tanggal` (`tanggal_pengajuan`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

if (!migration_pg_col_exists($pdo, 'transaksi', 'pengajuan_gu_id')) {
    $pdo->exec("ALTER TABLE `transaksi` ADD COLUMN `pengajuan_gu_id` INT NULL DEFAULT NULL AFTER `catatan_verifikasi`");
}

$idxStmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'transaksi' AND index_name = 'idx_transaksi_pengajuan_gu'");
$idxStmt->execute();
if ((int) $idxStmt->fetchColumn() === 0) {
    $pdo->exec("ALTER TABLE `transaksi` ADD INDEX `idx_transaksi_pengajuan_gu` (`pengajuan_gu_id`)");
}
