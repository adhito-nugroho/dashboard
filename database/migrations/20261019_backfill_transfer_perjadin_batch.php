<?php

declare(strict_types=1);

/**
 * Backfill: transaksi yang sudah dikonfigurasi SEBELUM fitur nama konfigurasi
 * (batch_id NULL) dikelompokkan menjadi batch per tanggal input transaksi,
 * mis. "Input 16/10/2026", agar muncul di "Konfigurasi Tersimpan" dan bisa diunduh.
 *
 * Idempoten: hanya menyentuh baris batch_id IS NULL; nama batch yang sudah
 * ada dipakai ulang. Aman dijalankan di database tanpa baris yatim (no-op).
 *
 * Catatan: $pdo tersedia dari migrate.php runner.
 */

if (!isset($pdo) && class_exists('Database')) {
    $pdo = Database::getConnection();
}

// Guard: butuh tabel & kolom dari migrasi 20261018.
$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
if (!in_array('transfer_perjadin_batch', $tables, true)) {
    return;
}
$cols = $pdo->query("SHOW COLUMNS FROM `transfer_perjadin`")->fetchAll(PDO::FETCH_COLUMN);
if (!in_array('batch_id', $cols, true)) {
    return;
}

$dates = $pdo->query("
    SELECT DISTINCT DATE(t.created_at) AS d
    FROM transfer_perjadin tp
    JOIN transaksi t ON t.id = tp.transaksi_id
    WHERE tp.batch_id IS NULL
    ORDER BY d
")->fetchAll(PDO::FETCH_COLUMN);

$find = $pdo->prepare("SELECT id FROM transfer_perjadin_batch WHERE nama = ?");
$add = $pdo->prepare("INSERT INTO transfer_perjadin_batch (nama) VALUES (?)");
$move = $pdo->prepare("
    UPDATE transfer_perjadin tp
    JOIN transaksi t ON t.id = tp.transaksi_id
    SET tp.batch_id = ?
    WHERE tp.batch_id IS NULL AND DATE(t.created_at) = ?
");

foreach ($dates as $d) {
    if ($d === null || $d === '') {
        continue;
    }
    $nama = 'Input ' . date('d/m/Y', strtotime((string) $d));

    $find->execute([$nama]);
    $id = $find->fetchColumn();
    if ($id === false) {
        $add->execute([$nama]);
        $id = (int) $pdo->lastInsertId();
    }
    $move->execute([(int) $id, $d]);
}
