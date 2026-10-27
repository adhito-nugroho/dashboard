<?php

declare(strict_types=1);

/**
 * Migration: Rincian rekening per pengajuan TU.
 *
 * Satu pengajuan TU kini bisa mencakup banyak rekening dalam satu sub kegiatan.
 * Kolom pengajuan_tu.rekening_id tetap dipertahankan (diisi rekening pertama)
 * agar kode lama yang masih membacanya tetap berjalan.
 */

if (!isset($pdo) && class_exists('Database')) {
    $pdo = Database::getConnection();
}

$pdo->exec("
    CREATE TABLE IF NOT EXISTS `pengajuan_tu_rekening` (
        `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `pengajuan_tu_id` INT UNSIGNED NOT NULL,
        `rekening_id`     INT UNSIGNED NOT NULL,
        `nominal`         DECIMAL(15,2) NOT NULL DEFAULT 0,
        `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_tu_rekening` (`pengajuan_tu_id`, `rekening_id`),
        KEY `idx_tu_rek_pengajuan` (`pengajuan_tu_id`),
        KEY `idx_tu_rek_rekening` (`rekening_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
      COMMENT='Rincian rekening & nominal per pengajuan TU'
");

// Backfill pengajuan lama: satu rekening = satu baris rincian.
$stmt = $pdo->prepare("
    INSERT INTO `pengajuan_tu_rekening` (`pengajuan_tu_id`, `rekening_id`, `nominal`)
    SELECT pt.id, pt.rekening_id, pt.kebutuhan
    FROM `pengajuan_tu` pt
    WHERE pt.rekening_id IS NOT NULL
      AND NOT EXISTS (
          SELECT 1 FROM `pengajuan_tu_rekening` tr
          WHERE tr.pengajuan_tu_id = pt.id
      )
");
$stmt->execute();
