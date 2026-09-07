<?php

declare(strict_types=1);

/**
 * Migration: tabel kalibrasi_kuitansi_elemen (posisi PER ELEMEN, mm).
 * Satu printer (tanpa printer_id). Tabel kalibrasi_kuitansi lama SENGAJA
 * tidak dihapus (histori) — generator baru tidak memakainya lagi.
 *
 * 16 elemen_key (sinkron dengan KuitansiPdfService::ELEMEN_KEYS):
 * 8 field + 3x (jabatan, nama, nip). Penerima tanpa NIP.
 */

function migration_kel_table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
    $stmt->execute([$table]);
    return (int) $stmt->fetchColumn() > 0;
}

if (!migration_kel_table_exists($pdo, 'kalibrasi_kuitansi_elemen')) {
    $pdo->exec("
        CREATE TABLE `kalibrasi_kuitansi_elemen` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `elemen_key` VARCHAR(40) NOT NULL UNIQUE,
            `label` VARCHAR(100) NOT NULL,
            `x_mm` DECIMAL(6,2) NOT NULL DEFAULT 0.00,
            `y_mm` DECIMAL(6,2) NOT NULL DEFAULT 0.00,
            `max_width_mm` DECIMAL(6,2) NULL DEFAULT NULL,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `updated_by` VARCHAR(100) NULL DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

// Offset global lama (kalibrasi_kuitansi id=1) sebagai titik awal, kalau ada.
$baseOx = 0.0;
$baseOy = 0.0;
try {
    $old = $pdo->query('SELECT `offset_x_mm`, `offset_y_mm` FROM `kalibrasi_kuitansi` ORDER BY `id` ASC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    if (is_array($old)) {
        $baseOx = (float) ($old['offset_x_mm'] ?? 0);
        $baseOy = (float) ($old['offset_y_mm'] ?? 0);
    }
} catch (Throwable $e) {
    // Tabel lama belum ada — mulai dari 0,0.
}

// [key, label, x, y, max_width_mm|null] — x/y dari config/koordinat_kuitansi.php + offset lama.
// Blok TTD dipecah: jabatan di y blok; nama/nip di posisi turunan lama
// (nama = jabatan + teks ±10mm + spasi 18mm; nip = nama + 6mm).
$seed = [
    ['no_bku',              'No. BKU / HAL',              150,  12, null],
    ['no_program',          'No. Program / Kegiatan',     150,  19, null],
    ['no_kegiatan',         'No. Kegiatan',               150,  26, null],
    ['terima_dari',         'Terima Dari',                 38,  42, null],
    ['jumlah_terbilang',    'Jumlah (Terbilang)',          38,  52, null],
    ['uraian',              'Uraian / Untuk Pembayaran',   15,  65, 185],
    ['terbilang_rp',        'Terbilang Rp (Angka)',       150, 100, null],
    ['tempat_tanggal',      'Tempat & Tanggal',           125, 112, null],
    ['ttd_kpa_jabatan',     'TTD KPA — Jabatan',           10, 118, null],
    ['ttd_kpa_nama',        'TTD KPA — Nama',              10, 146, null],
    ['ttd_kpa_nip',         'TTD KPA — NIP',               10, 152, null],
    ['ttd_bendahara_jabatan','TTD Bendahara — Jabatan',    78, 118, null],
    ['ttd_bendahara_nama',  'TTD Bendahara — Nama',        78, 146, null],
    ['ttd_bendahara_nip',   'TTD Bendahara — NIP',         78, 152, null],
    ['ttd_penerima_jabatan','TTD Penerima — Jabatan',     146, 118, null],
    ['ttd_penerima_nama',   'TTD Penerima — Nama',        146, 141, null],
];

$stmt = $pdo->prepare("
    INSERT INTO `kalibrasi_kuitansi_elemen` (`elemen_key`, `label`, `x_mm`, `y_mm`, `max_width_mm`, `updated_by`)
    VALUES (:k, :label, :x, :y, :w, 'seed')
    ON DUPLICATE KEY UPDATE `label` = VALUES(`label`)
");

foreach ($seed as [$key, $label, $x, $y, $w]) {
    $stmt->execute([
        ':k'     => $key,
        ':label' => $label,
        ':x'     => number_format($x + $baseOx, 2, '.', ''),
        ':y'     => number_format($y + $baseOy, 2, '.', ''),
        ':w'     => $w === null ? null : number_format((float) $w, 2, '.', ''),
    ]);
}
