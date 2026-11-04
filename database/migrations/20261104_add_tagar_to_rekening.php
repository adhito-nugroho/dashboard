<?php

declare(strict_types=1);

/**
 * Migration: tambah kolom `tagar` pada tabel rekening.
 *
 * Tagar diambil dari RKA SIPD, mis. `#NBS4_AGRI_INDUSTRI` pada baris
 * "[ # ] #NBS4_AGRI_INDUSTRI" di bawah kode rekening.
 *
 * Catatan: $pdo tersedia dari migrate.php runner.
 */

if (!function_exists('migration_rekening_column_exists')) {
    function migration_rekening_column_exists(PDO $pdo, string $table, string $column): bool
    {
        $stmt = $pdo->prepare('
            SELECT COUNT(*) FROM information_schema.columns
            WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?
        ');
        $stmt->execute([$table, $column]);
        return (int) $stmt->fetchColumn() > 0;
    }
}

if (!migration_rekening_column_exists($pdo, 'rekening', 'tagar')) {
    $pdo->exec("
        ALTER TABLE `rekening`
        ADD COLUMN `tagar` VARCHAR(100) NULL DEFAULT NULL
        COMMENT 'Tagar RKA SIPD, mis. #NBS4_AGRI_INDUSTRI'
        AFTER `nama_rekening`
    ");
}
