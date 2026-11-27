<?php

declare(strict_types=1);

/**
 * Migration: Memisahkan dana UP/GU dan TU pada tabel kas_bank.
 *
 * Latar belakang: setoran sisa TU dicatat sebagai kas_bank jenis='setoran',
 * padahal uangnya KELUAR dari kas bendahara kembali ke Kasda. Tanpa kolom
 * arah, getSaldoPerTanggal() menjumlahkan seluruh baris 'cair' sebagai
 * penerimaan sehingga saldo membengkak 2x nilai setoran dan kelebihannya
 * diserap ke komposisi "Uang GU".
 *
 * 1. `arah`      — masuk (menambah kas) / keluar (mengurangi kas)
 * 2. `jenis_dana` — UP_GU (operasional) / TU (Tambah Uang)
 */

if (!isset($pdo) && class_exists('Database')) {
    $pdo = Database::getConnection();
}

function migration_kas_bank_col_exists(PDO $pdo, string $column): bool
{
    $stmt = $pdo->prepare('
        SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = ?
          AND COLUMN_NAME  = ?
    ');
    $stmt->execute(['kas_bank', $column]);
    return (int) $stmt->fetchColumn() > 0;
}

if (!migration_kas_bank_col_exists($pdo, 'arah')) {
    $pdo->exec("
        ALTER TABLE `kas_bank`
            ADD COLUMN `arah` ENUM('masuk','keluar') NOT NULL DEFAULT 'masuk'
                COMMENT 'masuk = menambah kas, keluar = mengurangi kas'
                AFTER `jenis`
    ");
}

if (!migration_kas_bank_col_exists($pdo, 'jenis_dana')) {
    $pdo->exec("
        ALTER TABLE `kas_bank`
            ADD COLUMN `jenis_dana` ENUM('UP_GU','TU') NOT NULL DEFAULT 'UP_GU'
                COMMENT 'UP_GU = dana operasional (kena plafon), TU = Tambah Uang'
                AFTER `arah`
    ");
}

// ── Backfill data historis ────────────────────────────────────────────────
// Pencairan TU adalah dana TU yang masuk ke kas.
$pdo->exec("UPDATE `kas_bank` SET `jenis_dana` = 'TU' WHERE `jenis` = 'tu'");

// Setoran sisa TU adalah uang yang KELUAR dari kas kembali ke Kasda.
$pdo->exec("
    UPDATE `kas_bank` k
    INNER JOIN `pengajuan_tu` pt ON pt.`kas_bank_setoran_id` = k.`id`
    SET k.`arah` = 'keluar', k.`jenis_dana` = 'TU'
");

// Saldo awal adalah posisi pembuka, bukan penerimaan kantong tertentu.
$pdo->exec("
    UPDATE `kas_bank` SET `jenis_dana` = 'UP_GU'
    WHERE `jenis` IN ('saldo_awal', 'up', 'gu')
");

// ── Index untuk query saldo per arah & kantong dana ──────────────────────
$checkIdx = $pdo->prepare("
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'kas_bank'
      AND INDEX_NAME   = 'idx_kas_arah_dana'
");
$checkIdx->execute();
if ((int) $checkIdx->fetchColumn() === 0) {
    $pdo->exec('ALTER TABLE `kas_bank` ADD INDEX `idx_kas_arah_dana` (`arah`, `jenis_dana`)');
}
