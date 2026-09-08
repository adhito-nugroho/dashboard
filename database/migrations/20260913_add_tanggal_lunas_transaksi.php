<?php

declare(strict_types=1);

/**
 * Migration: tanggal lunas dibayar pada transaksi.
 * Diisi otomatis = tanggal verifikasi admin (bendahara); dikosongkan saat ditolak.
 * Backfill data lama yang sudah diverifikasi dari diverifikasi_at.
 */

function migration_lunas_col_exists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?');
    $stmt->execute([$table, $column]);
    return (int) $stmt->fetchColumn() > 0;
}

if (!migration_lunas_col_exists($pdo, 'transaksi', 'tanggal_lunas_dibayar')) {
    $pdo->exec('ALTER TABLE `transaksi` ADD COLUMN `tanggal_lunas_dibayar` DATE NULL DEFAULT NULL AFTER `tanggal`');
}

if (migration_lunas_col_exists($pdo, 'transaksi', 'diverifikasi_at')) {
    $pdo->exec("
        UPDATE `transaksi`
        SET `tanggal_lunas_dibayar` = DATE(`diverifikasi_at`)
        WHERE `status` = 'diverifikasi'
          AND `tanggal_lunas_dibayar` IS NULL
          AND `diverifikasi_at` IS NOT NULL
    ");
}
