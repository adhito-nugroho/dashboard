<?php

declare(strict_types=1);

/**
 * Migration: penanda rekening "selesai" pada rencana penyerapan.
 *
 * Rekening yang ditandai selesai tidak perlu diisi rencana penyerapannya lagi.
 * Status ini terpisah per tahun anggaran dan dapat dibatalkan.
 */

if (!isset($pdo) && class_exists('Database')) {
    $pdo = Database::getConnection();
}

$pdo->exec("
    CREATE TABLE IF NOT EXISTS `rencana_penyerapan_selesai` (
        `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `rekening_id` INT UNSIGNED NOT NULL,
        `tahun`       SMALLINT     NOT NULL,
        `keterangan`  VARCHAR(255) NULL,
        `created_by`  INT UNSIGNED NULL,
        `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,

        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_rps_rekening_tahun` (`rekening_id`, `tahun`),
        KEY `idx_rps_tahun` (`tahun`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
      COMMENT='Rekening yang dinyatakan selesai tanpa perlu rencana penyerapan'
");
