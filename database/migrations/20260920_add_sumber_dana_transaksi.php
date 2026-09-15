<?php

declare(strict_types=1);

/**
 * Migration: Sumber Dana pada Tabel Transaksi
 * - sumber_dana ENUM('UP','LS') NOT NULL DEFAULT 'UP'
 *   UP = via kas bendahara (mengurangi kas) — perilaku lama, default.
 *   LS = pembayaran langsung Bank Jatim Kas Daerah ke rekanan,
 *        TIDAK melalui & TIDAK mengurangi kas bendahara.
 */

function migration_sd_column_exists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?');
    $stmt->execute([$table, $column]);
    return (int) $stmt->fetchColumn() > 0;
}

if (!migration_sd_column_exists($pdo, 'transaksi', 'sumber_dana')) {
    $pdo->exec("ALTER TABLE `transaksi` ADD COLUMN `sumber_dana` ENUM('UP','LS') NOT NULL DEFAULT 'UP' AFTER `status`");
}
