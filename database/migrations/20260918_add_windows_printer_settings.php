<?php

declare(strict_types=1);

/**
 * Migration: tambah windows_printer_name dan paper_form_name pada tabel printer_kuitansi
 * Digunakan untuk integrasi silent print via SumatraPDF.
 */

if (!function_exists('migration_printer_col_exists_20260918')) {
    function migration_printer_col_exists_20260918(PDO $pdo, string $table, string $column): bool
    {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?');
        $stmt->execute([$table, $column]);
        return (int) $stmt->fetchColumn() > 0;
    }
}

if (!migration_printer_col_exists_20260918($pdo, 'printer_kuitansi', 'windows_printer_name')) {
    $pdo->exec('ALTER TABLE `printer_kuitansi` ADD COLUMN `windows_printer_name` VARCHAR(150) NULL DEFAULT NULL AFTER `is_default`');
}

if (!migration_printer_col_exists_20260918($pdo, 'printer_kuitansi', 'paper_form_name')) {
    $pdo->exec("ALTER TABLE `printer_kuitansi` ADD COLUMN `paper_form_name` VARCHAR(50) NULL DEFAULT 'Kuitansi' AFTER `windows_printer_name`");
}
