<?php

declare(strict_types=1);

/**
 * Migration: Field BKU & Surat Tugas pada Tabel Transaksi
 * - nama_penerima, jenis_transaksi, nomor_surat_tugas, tanggal_surat_tugas,
 *   tanggal_pelaksanaan, lokasi_kegiatan, surat_tugas_ref_id
 */

function migration_bku_column_exists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?');
    $stmt->execute([$table, $column]);
    return (int) $stmt->fetchColumn() > 0;
}

if (!migration_bku_column_exists($pdo, 'transaksi', 'nama_penerima')) {
    $pdo->exec("ALTER TABLE `transaksi` ADD COLUMN `nama_penerima` VARCHAR(150) NULL DEFAULT NULL AFTER `uraian`");
}

if (!migration_bku_column_exists($pdo, 'transaksi', 'jenis_transaksi')) {
    $pdo->exec("ALTER TABLE `transaksi` ADD COLUMN `jenis_transaksi` ENUM('perjalanan_dinas','belanja','honorarium','lainnya') NOT NULL DEFAULT 'lainnya' AFTER `nama_penerima`");
}

if (!migration_bku_column_exists($pdo, 'transaksi', 'nomor_surat_tugas')) {
    $pdo->exec("ALTER TABLE `transaksi` ADD COLUMN `nomor_surat_tugas` VARCHAR(100) NULL DEFAULT NULL AFTER `jenis_transaksi`");
}

if (!migration_bku_column_exists($pdo, 'transaksi', 'tanggal_surat_tugas')) {
    $pdo->exec("ALTER TABLE `transaksi` ADD COLUMN `tanggal_surat_tugas` DATE NULL DEFAULT NULL AFTER `nomor_surat_tugas`");
}

if (!migration_bku_column_exists($pdo, 'transaksi', 'tanggal_pelaksanaan')) {
    $pdo->exec("ALTER TABLE `transaksi` ADD COLUMN `tanggal_pelaksanaan` DATE NULL DEFAULT NULL AFTER `tanggal_surat_tugas`");
}

if (!migration_bku_column_exists($pdo, 'transaksi', 'lokasi_kegiatan')) {
    $pdo->exec("ALTER TABLE `transaksi` ADD COLUMN `lokasi_kegiatan` VARCHAR(255) NULL DEFAULT NULL AFTER `tanggal_pelaksanaan`");
}

if (!migration_bku_column_exists($pdo, 'transaksi', 'surat_tugas_ref_id')) {
    $pdo->exec("ALTER TABLE `transaksi` ADD COLUMN `surat_tugas_ref_id` INT NULL DEFAULT NULL AFTER `lokasi_kegiatan`");
}
