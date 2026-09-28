<?php

declare(strict_types=1);

/**
 * Migration: tambah kolom is_wrap pada kalibrasi_kuitansi_elemen.
 * - is_wrap NULL = ikut default (uraian=wrap, lainnya=single line).
 * - is_wrap 1 = paksa wrap (MultiCell, teks panjang turun ke baris baru).
 * - is_wrap 0 = single line (Cell, teks panjang meluber tanpa wrap).
 * Dikombinasikan dengan max_width_mm (NULL = lebar default config,
 * angka = override lebar per-elemen per-printer) sehingga tiap komponen
 * cetak bisa disetel panjang/pendek + wrap/tidak.
 */

if (!function_exists('migration_kalibrasi_wrap_col_exists_20260928')) {
    function migration_kalibrasi_wrap_col_exists_20260928(PDO $pdo, string $table, string $column): bool
    {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?');
        $stmt->execute([$table, $column]);
        return (int) $stmt->fetchColumn() > 0;
    }
}

if (migration_kalibrasi_wrap_col_exists_20260928($pdo, 'kalibrasi_kuitansi_elemen', 'max_width_mm')
    && !migration_kalibrasi_wrap_col_exists_20260928($pdo, 'kalibrasi_kuitansi_elemen', 'is_wrap')) {
    $pdo->exec('ALTER TABLE `kalibrasi_kuitansi_elemen` ADD COLUMN `is_wrap` TINYINT(1) NULL DEFAULT NULL AFTER `max_width_mm`');
}
