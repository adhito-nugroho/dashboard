<?php

declare(strict_types=1);

/**
 * Migration: posisi kalibrasi cetak lembar 2 SPPD per printer.
 *
 * Koordinat disimpan dalam mm pada halaman A4 (bukan ruang template), karena
 * hasil scan blanko diposisikan dengan offset kertas di kanvas kalibrasi.
 * max_width_mm / max_lines NULL = ikut default config/koordinat_sppd_lembar2.php.
 */

if (!isset($pdo) && class_exists('Database')) {
    $pdo = Database::getConnection();
}

$pdo->exec("
    CREATE TABLE IF NOT EXISTS `kalibrasi_sppd_lembar2_elemen` (
        `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `printer_id`    INT UNSIGNED NOT NULL,
        `elemen_key`    VARCHAR(40)  NOT NULL,
        `label`         VARCHAR(100) NULL,
        `x_mm`          DECIMAL(6,2) NOT NULL DEFAULT 0,
        `y_mm`          DECIMAL(6,2) NOT NULL DEFAULT 0,
        `max_width_mm`  DECIMAL(6,2) NULL,
        `max_lines`     TINYINT UNSIGNED NULL,
        `updated_by`    VARCHAR(100) NULL,
        `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_kalib_sppd2_printer_elemen` (`printer_id`, `elemen_key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
      COMMENT='Posisi elemen cetak lembar 2 SPPD per printer (mm pada A4)'
");
