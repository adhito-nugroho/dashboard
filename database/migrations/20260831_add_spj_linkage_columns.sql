-- ============================================================
-- Migration: Tambah kolom penghubung SPJ Rincian Biaya
-- ============================================================
-- 1. Kolom pegawai_nip di tabel transaksi
--    (NULL-able agar baris lama tidak terpengaruh)
ALTER TABLE `transaksi`
    ADD COLUMN IF NOT EXISTS `pegawai_nip` VARCHAR(40) DEFAULT NULL
        COMMENT 'NIP pegawai jika transaksi = perjalanan dinas per pegawai'
        AFTER `nama_penerima`;

-- 2. Kolom transaksi_id di rincian_biaya_perjalanan_dinas
--    Hubungkan satu baris rincian biaya ke satu baris transaksi.
--    NULL = rincian biaya lama yang diinput sebelum fitur ini ada (tetap valid).
ALTER TABLE `rincian_biaya_perjalanan_dinas`
    ADD COLUMN IF NOT EXISTS `transaksi_id` INT UNSIGNED DEFAULT NULL
        COMMENT 'FK ke transaksi.id — NULL jika diinput mandiri dari /spj'
        AFTER `nomor_surat`;

-- Index untuk join dari sisi transaksi
ALTER TABLE `rincian_biaya_perjalanan_dinas`
    ADD KEY IF NOT EXISTS `idx_transaksi_id` (`transaksi_id`);
