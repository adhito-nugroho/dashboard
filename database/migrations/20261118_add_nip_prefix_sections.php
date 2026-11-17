<?php

declare(strict_types=1);

/**
 * Migration: kontrol awalan "NIP." per bagian lembar 2 SPPD.
 *
 * Kolom baru pada sppd_lembar2_pejabat (1 = tampilkan awalan, 0 = nomor saja):
 * - nip_prefix_i  : blok tanda tangan Romawi I (Kepala CDK)
 * - nip_prefix_ii : kolom pejabat instansi tujuan (Tiba di / Berangkat dari)
 * - nip_prefix_vi : blok tanda tangan Romawi VI (Kepala CDK)
 *
 * Data lama: awalan mengikuti nilai `tampilkan_nip` yang berlaku sebelumnya.
 */

if (!function_exists('migration_sppd2_nip_prefix_col_exists_20261118')) {
    function migration_sppd2_nip_prefix_col_exists_20261118(PDO $pdo, string $table, string $column): bool
    {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?');
        $stmt->execute([$table, $column]);
        return (int) $stmt->fetchColumn() > 0;
    }
}

$table = 'sppd_lembar2_pejabat';
$columns = [
    'nip_prefix_i'  => "TINYINT(1) NOT NULL DEFAULT 1 AFTER `tampilkan_nip`",
    'nip_prefix_ii' => "TINYINT(1) NOT NULL DEFAULT 1 AFTER `nip_prefix_i`",
    'nip_prefix_vi' => "TINYINT(1) NOT NULL DEFAULT 1 AFTER `nip_prefix_ii`",
];

$added = false;
foreach ($columns as $column => $definition) {
    if (!migration_sppd2_nip_prefix_col_exists_20261118($pdo, $table, $column)) {
        $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
        $added = true;
    }
}

// Inisialisasi sekali saja saat kolom baru dibuat, agar pilihan pengguna
// tidak tertimpa bila migration dijalankan ulang.
if ($added && migration_sppd2_nip_prefix_col_exists_20261118($pdo, $table, 'tampilkan_nip')) {
    $pdo->exec(
        "UPDATE `{$table}`
         SET `nip_prefix_i` = `tampilkan_nip`,
             `nip_prefix_ii` = `tampilkan_nip`,
             `nip_prefix_vi` = `tampilkan_nip`"
    );
}
