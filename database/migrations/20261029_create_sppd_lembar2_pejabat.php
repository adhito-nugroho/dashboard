<?php

declare(strict_types=1);

/**
 * Migration: Pejabat instansi tujuan untuk cetak lembar 2 SPPD.
 *
 * Satu Surat Tugas cukup menyimpan satu pejabat tujuan (jabatan + nama + NIP).
 * Data ini dipakai untuk kedua kolom stempel pada lembar 2: "Tiba di" dan
 * "Berangkat dari".
 */

if (!isset($pdo) && class_exists('Database')) {
    $pdo = Database::getConnection();
}

$pdo->exec("
    CREATE TABLE IF NOT EXISTS `sppd_lembar2_pejabat` (
        `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `surat_tugas_id`  INT UNSIGNED NOT NULL,
        `jabatan`         VARCHAR(255) NOT NULL,
        `nama`            VARCHAR(255) NOT NULL,
        `nip`             VARCHAR(30)  NOT NULL,
        `updated_by`      VARCHAR(100) NULL,
        `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_sppd2_surat_tugas` (`surat_tugas_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
      COMMENT='Pejabat instansi tujuan pada cetak lembar 2 SPPD'
");
