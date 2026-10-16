<?php

declare(strict_types=1);

/**
 * Migration: ubah transfer_perjadin agar berbasis TRANSAKSI (bukan Surat Tugas).
 *
 * Konfigurasi transfer kini menempel ke satu baris transaksi perjalanan dinas:
 *   - transfer_ke : nama penerima transfer untuk transaksi tsb.
 *   - potongan    : potongan untuk transaksi tsb (manual, admin).
 *
 * Tabel lama (per surat_tugas_id + pegawai_nip) dibuat baru sesi ini dan masih
 * kosong, jadi aman dibuat ulang dengan skema baru.
 *
 * Catatan: $pdo tersedia dari migrate.php runner.
 */

if (!isset($pdo) && class_exists('Database')) {
    $pdo = Database::getConnection();
}

$pdo->exec("DROP TABLE IF EXISTS `transfer_perjadin`");

$pdo->exec("
    CREATE TABLE `transfer_perjadin` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `transaksi_id` INT NOT NULL,
        `transfer_ke` VARCHAR(150) NOT NULL DEFAULT '',
        `potongan` DECIMAL(15, 2) NOT NULL DEFAULT 0,
        `created_by` INT NULL DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY `uq_transfer_perjadin_transaksi` (`transaksi_id`),
        INDEX `idx_transfer_perjadin_ke` (`transfer_ke`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");
