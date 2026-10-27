<?php

declare(strict_types=1);

/**
 * Migration: Tambah kolom rekening_id ke pengajuan_tu
 * Agar pengajuan TU bisa menyertakan referensi rekening anggaran
 * (Program → Kegiatan → Sub Kegiatan → Rekening) seperti form transaksi.
 */

if (!isset($pdo) && class_exists('Database')) {
    $pdo = Database::getConnection();
}

$colCheck = $pdo->prepare("
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'pengajuan_tu'
      AND COLUMN_NAME  = 'rekening_id'
");
$colCheck->execute();
if ((int) $colCheck->fetchColumn() === 0) {
    $pdo->exec("
        ALTER TABLE `pengajuan_tu`
            ADD COLUMN `rekening_id` INT UNSIGNED DEFAULT NULL
                COMMENT 'FK ke rekening.id — referensi rekening anggaran yang akan digunakan TU'
                AFTER `seksi_id`,
            ADD KEY `idx_tu_rekening` (`rekening_id`)
    ");
}
