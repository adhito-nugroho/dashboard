<?php

declare(strict_types=1);

/**
 * Migration: konfigurasi transfer bernama (batch).
 *
 * - Tabel baru transfer_perjadin_batch: satu baris per konfigurasi tersimpan
 *   (nama unik, pemberi nama = admin saat menyimpan).
 * - Kolom baru transfer_perjadin.batch_id: menandai konfigurasi mana
 *   sebuah transaksi ikut. Satu transaksi hanya bisa ikut satu konfigurasi
 *   (transaksi_id UNIQUE), sehingga transaksi yang sudah masuk konfigurasi
 *   tidak ditampilkan lagi pada daftar konfigurasi baru.
 *
 * Catatan: $pdo tersedia dari migrate.php runner.
 */

if (!isset($pdo) && class_exists('Database')) {
    $pdo = Database::getConnection();
}

$pdo->exec("
    CREATE TABLE IF NOT EXISTS `transfer_perjadin_batch` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `nama` VARCHAR(150) NOT NULL,
        `created_by` INT NULL DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `uq_transfer_perjadin_batch_nama` (`nama`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

$cols = $pdo->query("SHOW COLUMNS FROM `transfer_perjadin`")->fetchAll(PDO::FETCH_COLUMN);
if (!in_array('batch_id', $cols, true)) {
    $pdo->exec("
        ALTER TABLE `transfer_perjadin`
        ADD COLUMN `batch_id` INT NULL DEFAULT NULL AFTER `transaksi_id`,
        ADD INDEX `idx_transfer_perjadin_batch` (`batch_id`)
    ");
}
