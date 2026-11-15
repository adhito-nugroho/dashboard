<?php

declare(strict_types=1);

/**
 * Migration: Rencana Penyerapan Anggaran
 *
 * Satu baris = satu rencana kegiatan untuk sebuah rekening pada tahun tertentu.
 * Digunakan untuk menyusun timeline penyerapan 12 bulan per kode rekening:
 *   - bentuk_kegiatan : mis. Pengadaan Barang, Pengadaan Bibit
 *   - mekanisme       : GU (Ganti Uang), TU (Tambah Uang), LS (Langsung)
 *   - bulan_mulai/selesai : rentang pelaksanaan dalam tahun anggaran
 *   - nilai_rencana   : rencana nilai penyerapan
 *
 * Catatan: $pdo tersedia dari migrate.php runner.
 */

if (!isset($pdo) && class_exists('Database')) {
    $pdo = Database::getConnection();
}

$pdo->exec("
    CREATE TABLE IF NOT EXISTS `rencana_penyerapan` (
        `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `rekening_id`     INT UNSIGNED NOT NULL COMMENT 'FK ke rekening.id',
        `tahun`           SMALLINT     NOT NULL COMMENT 'Tahun anggaran',
        `bentuk_kegiatan` VARCHAR(150) NOT NULL COMMENT 'Bentuk kegiatan, mis. Pengadaan Barang / Pengadaan Bibit',
        `uraian`          VARCHAR(500) NULL DEFAULT NULL COMMENT 'Detail singkat rencana kegiatan',
        `mekanisme`       ENUM('GU','TU','LS') NOT NULL DEFAULT 'GU'
                          COMMENT 'Mekanisme pembayaran: GU=Ganti Uang, TU=Tambah Uang, LS=Langsung',
        `bulan_mulai`     TINYINT      NOT NULL DEFAULT 1 COMMENT 'Bulan awal pelaksanaan (1-12)',
        `bulan_selesai`   TINYINT      NOT NULL DEFAULT 1 COMMENT 'Bulan akhir pelaksanaan (1-12)',
        `nilai_rencana`   DECIMAL(18,2) NOT NULL DEFAULT 0 COMMENT 'Rencana nilai penyerapan (Rp)',
        `keterangan`      VARCHAR(255) NULL DEFAULT NULL,
        `created_by`      INT UNSIGNED NULL DEFAULT NULL COMMENT 'users.id pembuat rencana',
        `created_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_rencana_rek_tahun` (`rekening_id`, `tahun`),
        KEY `idx_rencana_tahun`     (`tahun`),
        KEY `idx_rencana_mekanisme` (`mekanisme`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
      COMMENT='Rencana timeline penyerapan anggaran per rekening'
");
