<?php

declare(strict_types=1);

/**
 * Migration: Fitur PAPBD — riwayat perubahan pagu
 * - Tabel baru `pagu_riwayat` mencatat setiap perubahan nilai_pagu
 *   (nilai_sebelum -> nilai_sesudah + selisih + jenis + keterangan + user + waktu).
 * - Nilai APBD awal = nilai_sebelum dari log pertama; jika belum ada log,
 *   nilai saat ini dianggap APBD awal (backward compatible).
 *
 * Catatan: $pdo tersedia dari migrate.php runner.
 */

if (!function_exists('migration_papu_table_exists')) {
    function migration_papu_table_exists(PDO $pdo, string $table): bool
    {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
        $stmt->execute([$table]);
        return (int) $stmt->fetchColumn() > 0;
    }
}

if (migration_papu_table_exists($pdo, 'pagu')) {
    if (!migration_papu_table_exists($pdo, 'pagu_riwayat')) {
        $pdo->exec("
            CREATE TABLE `pagu_riwayat` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `pagu_id` INT UNSIGNED NOT NULL COMMENT 'FK ke pagu.id saat perubahan terjadi',
                `rekening_id` INT UNSIGNED NOT NULL COMMENT 'snapshot rekening saat perubahan',
                `tahun` INT NOT NULL,
                `nilai_sebelum` DECIMAL(20,2) NOT NULL DEFAULT 0,
                `nilai_sesudah` DECIMAL(20,2) NOT NULL DEFAULT 0,
                `selisih` DECIMAL(20,2) NOT NULL DEFAULT 0 COMMENT 'nilai_sesudah - nilai_sebelum (+ tambah, - kurang)',
                `jenis` VARCHAR(20) NOT NULL DEFAULT 'PAPBD' COMMENT 'APBD, PAPBD, KOREKSI',
                `keterangan` VARCHAR(255) NULL DEFAULT NULL,
                `created_by` INT UNSIGNED NULL DEFAULT NULL COMMENT 'users.id pelaku perubahan',
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY `idx_pagu_riwayat_pagu` (`pagu_id`),
                KEY `idx_pagu_riwayat_rek_thn` (`rekening_id`, `tahun`),
                KEY `idx_pagu_riwayat_tahun` (`tahun`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            COMMENT='Riwayat perubahan pagu (APBD -> PAPBD)'
        ");
    }
}
