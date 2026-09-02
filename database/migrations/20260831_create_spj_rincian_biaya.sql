-- ============================================================
-- Migration: Modul SPJ Rincian Biaya Perjalanan Dinas
-- Database  : db_anggaran
-- Dibuat    : 2026
-- ============================================================
-- Tabel header rincian biaya perjalanan dinas, per pegawai per ST
CREATE TABLE IF NOT EXISTS `rincian_biaya_perjalanan_dinas` (
    `id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT,

    -- Hubungkan satu baris rincian biaya ke satu baris transaksi (opsional)
    `transaksi_id`        INT UNSIGNED DEFAULT NULL COMMENT 'FK ke transaksi.id — NULL jika diinput mandiri dari /spj',

    -- Referensi ke db_surat_tugas (disimpan sebagai value, bukan FK lintas DB)
    `surat_tugas_id`      INT UNSIGNED NOT NULL COMMENT 'ID dari surat_tugas.id di db_surat_tugas',
    `nomor_surat`         VARCHAR(120) NOT NULL  COMMENT 'Cache nomor_surat dari surat_tugas',

    -- Identitas pegawai (disimpan sebagai cache agar tidak bergantung lintas DB)
    `pegawai_nip`         VARCHAR(40)  NOT NULL  COMMENT 'NIP pegawai dari pegawai_tugas.nip',
    `pegawai_nama`        VARCHAR(150) NOT NULL  COMMENT 'Nama pegawai dari tabel pegawai',
    `pegawai_pangkat`     VARCHAR(100) DEFAULT NULL,
    `pegawai_jabatan`     VARCHAR(150) DEFAULT NULL,

    -- Perhitungan SPPD Rampung
    `ditetapkan_sejumlah` DECIMAL(15,2) NOT NULL DEFAULT 0 COMMENT 'Jumlah yang ditetapkan (Rp)',
    `dibayar_semula`      DECIMAL(15,2) NOT NULL DEFAULT 0 COMMENT 'Yang telah dibayar semula (Rp)',
    -- sisa_kurang_lebih dihitung: ditetapkan_sejumlah - dibayar_semula (computed di PHP, tidak disimpan)

    -- Metadata cetak
    `tempat_tanggal`      VARCHAR(100) DEFAULT NULL COMMENT 'Contoh: Bojonegoro, 21 April 2026',

    -- Audit
    `created_by`          INT UNSIGNED DEFAULT NULL COMMENT 'user_id yang membuat',
    `updated_by`          INT UNSIGNED DEFAULT NULL COMMENT 'user_id yang terakhir update',
    `created_at`          TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`          TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),

    -- Satu pegawai hanya boleh punya 1 rincian per Surat Tugas
    UNIQUE KEY `uq_st_pegawai` (`surat_tugas_id`, `pegawai_nip`),

    KEY `idx_transaksi_id`   (`transaksi_id`),
    KEY `idx_surat_tugas_id` (`surat_tugas_id`),
    KEY `idx_pegawai_nip`    (`pegawai_nip`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Header rincian biaya perjalanan dinas per pegawai per Surat Tugas';


-- Tabel detail komponen biaya (one-to-many dari header)
CREATE TABLE IF NOT EXISTS `rincian_biaya_perjalanan_dinas_detail` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `rincian_biaya_id` INT UNSIGNED NOT NULL COMMENT 'FK ke rincian_biaya_perjalanan_dinas.id',

    `nama_komponen`   VARCHAR(150) NOT NULL  COMMENT 'Misal: Uang Harian, BBM, Tol, Hotel',
    `harga_satuan`    DECIMAL(15,2) NOT NULL DEFAULT 0,
    `jumlah_hari`     DECIMAL(5,1)  DEFAULT NULL COMMENT 'Opsional: jumlah hari/unit. NULL = tidak dihitung harian',
    `jumlah`          DECIMAL(15,2) NOT NULL DEFAULT 0 COMMENT 'harga_satuan * jumlah_hari (atau manual jika jumlah_hari NULL)',
    `keterangan`      VARCHAR(255)  DEFAULT NULL,
    `urutan`          TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Urutan tampil baris',

    PRIMARY KEY (`id`),
    KEY `idx_rincian_biaya_id` (`rincian_biaya_id`),

    CONSTRAINT `fk_detail_rincian_biaya`
        FOREIGN KEY (`rincian_biaya_id`)
        REFERENCES `rincian_biaya_perjalanan_dinas` (`id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Detail komponen biaya perjalanan dinas per baris';
