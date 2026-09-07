<?php

declare(strict_types=1);

/**
 * Migration: profil kalibrasi per printer.
 * - Tabel baru `printer_kuitansi` (id, nama unique, keterangan, is_default).
 *   Seed: Epson L3110 (default) + Epson LQ-310.
 * - Kolom `printer_id` di `kalibrasi_kuitansi_elemen`; baris existing ikut
 *   printer default. Unique lama (elemen_key) diganti (printer_id, elemen_key).
 */

function migration_printer_table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
    $stmt->execute([$table]);
    return (int) $stmt->fetchColumn() > 0;
}

function migration_printer_col_exists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?');
    $stmt->execute([$table, $column]);
    return (int) $stmt->fetchColumn() > 0;
}

if (!migration_printer_table_exists($pdo, 'printer_kuitansi')) {
    $pdo->exec("
        CREATE TABLE `printer_kuitansi` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `nama` VARCHAR(100) NOT NULL UNIQUE,
            `keterangan` VARCHAR(255) NULL DEFAULT NULL,
            `is_default` TINYINT(1) NOT NULL DEFAULT 0,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

$stmt = $pdo->prepare('INSERT IGNORE INTO `printer_kuitansi` (`nama`, `keterangan`, `is_default`) VALUES (:nama, :ket, :def)');
$stmt->execute([':nama' => 'Epson L3110', ':ket' => 'Inkjet — kalibrasi awal', ':def' => 0]);
$stmt->execute([':nama' => 'Epson LQ-310', ':ket' => 'Dot matrix 24-pin', ':def' => 0]);

// Pastikan tepat satu default (L3110 jika belum ada default sama sekali).
$defCount = (int) $pdo->query('SELECT COUNT(*) FROM `printer_kuitansi` WHERE `is_default` = 1')->fetchColumn();
if ($defCount === 0) {
    $pdo->exec("UPDATE `printer_kuitansi` SET `is_default` = 1 WHERE `nama` = 'Epson L3110' LIMIT 1");
}

if (migration_printer_table_exists($pdo, 'kalibrasi_kuitansi_elemen')) {
    if (!migration_printer_col_exists($pdo, 'kalibrasi_kuitansi_elemen', 'printer_id')) {
        $pdo->exec('ALTER TABLE `kalibrasi_kuitansi_elemen` ADD COLUMN `printer_id` INT UNSIGNED NOT NULL DEFAULT 1 AFTER `id`');
    }
    $defaultId = (int) $pdo->query('SELECT `id` FROM `printer_kuitansi` WHERE `is_default` = 1 LIMIT 1')->fetchColumn();
    if ($defaultId > 0) {
        // Semua baris lama berasal dari era satu-printer -> ikut printer default.
        $pdo->exec("UPDATE `kalibrasi_kuitansi_elemen` SET `printer_id` = {$defaultId}");
    }

    // Ganti unique (elemen_key) -> (printer_id, elemen_key).
    $idx = $pdo->query("SHOW INDEX FROM `kalibrasi_kuitansi_elemen` WHERE Key_name = 'elemen_key'")->fetchAll(PDO::FETCH_ASSOC);
    if (!empty($idx)) {
        $pdo->exec('ALTER TABLE `kalibrasi_kuitansi_elemen` DROP INDEX `elemen_key`');
    }
    $idx2 = $pdo->query("SHOW INDEX FROM `kalibrasi_kuitansi_elemen` WHERE Key_name = 'uq_printer_elemen'")->fetchAll(PDO::FETCH_ASSOC);
    if (empty($idx2)) {
        $pdo->exec('ALTER TABLE `kalibrasi_kuitansi_elemen` ADD UNIQUE KEY `uq_printer_elemen` (`printer_id`, `elemen_key`)');
    }
}
