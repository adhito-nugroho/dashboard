<?php

declare(strict_types=1);

/**
 * Migration: Tambah kolom penghubung SPJ Rincian Biaya
 * - pegawai_nip di tabel transaksi
 * - transaksi_id di tabel rincian_biaya_perjalanan_dinas
 * - index idx_transaksi_id
 *
 * Catatan: $pdo tersedia dari migrate.php runner.
 */

if (!function_exists('migration_spj_linkage_column_exists')) {
    function migration_spj_linkage_column_exists(PDO $pdo, string $table, string $column): bool
    {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?');
        $stmt->execute([$table, $column]);
        return (int) $stmt->fetchColumn() > 0;
    }
}

if (!function_exists('migration_spj_linkage_index_exists')) {
    function migration_spj_linkage_index_exists(PDO $pdo, string $table, string $index): bool
    {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?');
        $stmt->execute([$table, $index]);
        return (int) $stmt->fetchColumn() > 0;
    }
}

if (!function_exists('migration_spj_linkage_table_exists')) {
    function migration_spj_linkage_table_exists(PDO $pdo, string $table): bool
    {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
        $stmt->execute([$table]);
        return (int) $stmt->fetchColumn() > 0;
    }
}

// 1. Kolom pegawai_nip di tabel transaksi
if (migration_spj_linkage_table_exists($pdo, 'transaksi')) {
    if (!migration_spj_linkage_column_exists($pdo, 'transaksi', 'pegawai_nip')) {
        $pdo->exec("ALTER TABLE `transaksi` ADD COLUMN `pegawai_nip` VARCHAR(40) NULL DEFAULT NULL COMMENT 'NIP pegawai jika transaksi = perjalanan dinas per pegawai' AFTER `nama_penerima`");
    }
}

// 2. Kolom transaksi_id di rincian_biaya_perjalanan_dinas
if (migration_spj_linkage_table_exists($pdo, 'rincian_biaya_perjalanan_dinas')) {
    if (!migration_spj_linkage_column_exists($pdo, 'rincian_biaya_perjalanan_dinas', 'transaksi_id')) {
        $pdo->exec("ALTER TABLE `rincian_biaya_perjalanan_dinas` ADD COLUMN `transaksi_id` INT UNSIGNED NULL DEFAULT NULL COMMENT 'FK ke transaksi.id — NULL jika diinput mandiri dari /spj' AFTER `nomor_surat`");
    }

    // 3. Index idx_transaksi_id
    if (!migration_spj_linkage_index_exists($pdo, 'rincian_biaya_perjalanan_dinas', 'idx_transaksi_id')) {
        $pdo->exec("ALTER TABLE `rincian_biaya_perjalanan_dinas` ADD KEY `idx_transaksi_id` (`transaksi_id`)");
    }
}
