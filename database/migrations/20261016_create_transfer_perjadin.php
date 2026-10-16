<?php

declare(strict_types=1);

/**
 * Migration: Transfer Perjalanan Dinas.
 *
 * Menyimpan konfigurasi transfer untuk setiap Surat Tugas (dari db_surat_tugas):
 * - satu pegawai per Surat Tugas ditandai sebagai penerima transfer (is_transfer_ke).
 * - potongan per pegawai diisi manual oleh admin.
 *
 * Penerimaan (gross) dihitung dari transaksi perjalanan dinas pada db_anggaran,
 * tidak disimpan di sini. Penerimaan bersih = penerimaan - potongan.
 *
 * Catatan: $pdo tersedia dari migrate.php runner.
 */

if (!isset($pdo) && class_exists('Database')) {
    $pdo = Database::getConnection();
}

$pdo->exec("
    CREATE TABLE IF NOT EXISTS `transfer_perjadin` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `surat_tugas_id` INT NOT NULL,
        `nomor_surat` VARCHAR(100) NOT NULL DEFAULT '',
        `pegawai_nip` VARCHAR(25) NOT NULL,
        `pegawai_nama` VARCHAR(150) NOT NULL DEFAULT '',
        `is_transfer_ke` TINYINT(1) NOT NULL DEFAULT 0,
        `potongan` DECIMAL(15, 2) NOT NULL DEFAULT 0,
        `created_by` INT NULL DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY `uq_transfer_perjadin_st_nip` (`surat_tugas_id`, `pegawai_nip`),
        INDEX `idx_transfer_perjadin_st` (`surat_tugas_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");
