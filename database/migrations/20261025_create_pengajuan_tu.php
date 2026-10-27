<?php

declare(strict_types=1);

/**
 * Migration: Tambah Uang (TU)
 *
 * 1. Tabel `pengajuan_tu`  — satu baris = satu siklus TU
 *    Alur: Seksi mengajukan TU → admin setujui → SP2D-TU cair (masuk kas_bank jenis 'tu')
 *          → seksi belanja → transaksi dikaitkan ke pengajuan_tu_id
 *          → batas waktu habis → sisa yang tidak dipakai disetor ke kas (kas_bank jenis 'setoran')
 *
 * 2. Kolom `pengajuan_tu_id` di `transaksi` — menandai belanja yang dibayar dari dana TU ini.
 *
 * 3. Enum kas_bank.jenis ditambah nilai 'tu'.
 */

if (!isset($pdo) && class_exists('Database')) {
    $pdo = Database::getConnection();
}

// ── Tabel pengajuan_tu ─────────────────────────────────────────────────────
$pdo->exec("
    CREATE TABLE IF NOT EXISTS `pengajuan_tu` (
        `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,

        -- Identifikasi pengajuan
        `nomor_pengajuan`   VARCHAR(100)  NOT NULL COMMENT 'Nomor SPP-TU, contoh: 094/TU/001/X/2026',
        `tanggal_pengajuan` DATE          NOT NULL COMMENT 'Tanggal seksi mengajukan TU',
        `seksi_id`          INT UNSIGNED  NOT NULL COMMENT 'Seksi yang mengajukan TU',
        `tahun`             SMALLINT      NOT NULL COMMENT 'Tahun anggaran',
        `bulan`             TINYINT       NOT NULL COMMENT 'Bulan kebutuhan TU (1-12)',
        `kebutuhan`         DECIMAL(15,2) NOT NULL DEFAULT 0 COMMENT 'Nilai TU yang diajukan (Rp)',
        `keperluan`         VARCHAR(500)  NOT NULL COMMENT 'Uraian keperluan / kegiatan yang akan dibiayai',

        -- Status pengajuan
        `status`            ENUM('diajukan','disetujui','cair','selesai','ditolak')
                            NOT NULL DEFAULT 'diajukan'
                            COMMENT 'diajukan=menunggu persetujuan, disetujui=disetujui admin belum cair, cair=SP2D-TU sudah cair, selesai=SPJ-TU sudah di-GU-kan/disetor, ditolak',

        -- Persetujuan admin
        `disetujui_by`      INT UNSIGNED  NULL COMMENT 'user_id admin yang menyetujui',
        `disetujui_at`      TIMESTAMP     NULL,
        `catatan_admin`     VARCHAR(255)  NULL COMMENT 'Catatan admin saat setujui/tolak',

        -- Pencairan (SP2D-TU)
        `nomor_sp2d`        VARCHAR(100)  NULL COMMENT 'Nomor SP2D-TU dari BUD',
        `tanggal_cair`      DATE          NULL COMMENT 'Tanggal SP2D-TU cair ke rekening bendahara',
        `nominal_cair`      DECIMAL(15,2) NULL COMMENT 'Nilai SP2D-TU yang benar-benar cair',
        `kas_bank_id`       INT UNSIGNED  NULL COMMENT 'FK ke kas_bank.id (jenis=tu) saat dicairkan',

        -- Batas waktu SPJ
        `batas_spj`         DATE          NULL COMMENT 'Batas akhir pertanggungjawaban TU (default +30 hari dari tanggal_cair)',

        -- Penyelesaian
        `nominal_digunakan` DECIMAL(15,2) NULL COMMENT 'Total transaksi yang dikaitkan ke TU ini (dihitung otomatis)',
        `nominal_disetor`   DECIMAL(15,2) NULL DEFAULT 0 COMMENT 'Sisa TU yang dikembalikan ke kas (baris kas_bank setoran)',
        `kas_bank_setoran_id` INT UNSIGNED NULL COMMENT 'FK ke kas_bank.id (jenis=setoran) saat sisa disetor',

        -- Audit
        `created_by`        INT UNSIGNED  NULL,
        `created_at`        TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at`        TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_nomor_pengajuan_tu` (`nomor_pengajuan`),
        KEY `idx_tu_seksi`  (`seksi_id`),
        KEY `idx_tu_status` (`status`),
        KEY `idx_tu_periode` (`tahun`, `bulan`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
      COMMENT='Siklus Tambah Uang (TU): pengajuan s/d penyelesaian setoran sisa'
");

// ── Kolom pengajuan_tu_id di transaksi ─────────────────────────────────────
$pdo->exec("
    ALTER TABLE `transaksi`
        ADD COLUMN IF NOT EXISTS `pengajuan_tu_id` INT UNSIGNED DEFAULT NULL
            COMMENT 'FK ke pengajuan_tu.id — NULL = bukan dari dana TU'
            AFTER `pengajuan_gu_id`
");

// ── Tambah nilai 'tu' pada enum kas_bank.jenis ────────────────────────────
// MySQL tidak bisa DROP/ADD enum langsung; harus MODIFY COLUMN ulang.
$pdo->exec("
    ALTER TABLE `kas_bank`
        MODIFY COLUMN `jenis`
            ENUM('saldo_awal', 'up', 'gu', 'tu', 'setoran', 'lainnya')
            NOT NULL DEFAULT 'gu'
");
