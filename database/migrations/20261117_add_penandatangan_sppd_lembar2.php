<?php

declare(strict_types=1);

/**
 * Migration: data penandatangan lain pada cetak lembar 2 SPPD.
 *
 * Ditambahkan ke tabel sppd_lembar2_pejabat (satu baris per Surat Tugas):
 * - kepala_*      : Kepala CDK Wilayah Bojonegoro (tanda tangan bagian I & VI)
 * - kpa_*         : Kuasa Pengguna Anggaran (blok paling bawah)
 * - tampilkan_nip : 0 = tulisan "NIP. <nomor>" tidak dicetak pada penandatangan
 */

if (!function_exists('migration_sppd2_penandatangan_col_exists_20261117')) {
    function migration_sppd2_penandatangan_col_exists_20261117(PDO $pdo, string $table, string $column): bool
    {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?');
        $stmt->execute([$table, $column]);
        return (int) $stmt->fetchColumn() > 0;
    }
}

$table = 'sppd_lembar2_pejabat';
$columns = [
    'kepala_jabatan' => "VARCHAR(255) NOT NULL DEFAULT '' AFTER `nip`",
    'kepala_nama'    => "VARCHAR(255) NOT NULL DEFAULT '' AFTER `kepala_jabatan`",
    'kepala_nip'     => "VARCHAR(30)  NOT NULL DEFAULT '' AFTER `kepala_nama`",
    'kpa_nama'       => "VARCHAR(255) NOT NULL DEFAULT '' AFTER `kepala_nip`",
    'kpa_nip'        => "VARCHAR(30)  NOT NULL DEFAULT '' AFTER `kpa_nama`",
    'tampilkan_nip'  => "TINYINT(1) NOT NULL DEFAULT 1 AFTER `kpa_nip`",
];

foreach ($columns as $column => $definition) {
    if (!migration_sppd2_penandatangan_col_exists_20261117($pdo, $table, $column)) {
        $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
    }
}
