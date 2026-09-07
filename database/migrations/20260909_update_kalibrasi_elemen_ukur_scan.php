<?php

declare(strict_types=1);

/**
 * Migration: selaraskan posisi kalibrasi_kuitansi_elemen dengan hasil ukur
 * scan NCR asli (215x165mm). Perubahan utama:
 * - no_bku/no_program/no_kegiatan masuk sel kanan (slash "/" pre-printed)
 * - jumlah_terbilang ke box miring (x=48), uraian ke area kosong (x=37,y=72,w=170)
 * - terbilang_rp ke box angka KIRI (x=45,y=96) — sebelumnya keliru di kanan
 * - tempat_tanggal y=114; TTD: jabatan y=122, nama di garis titik-titik y=150, NIP y=156
 *
 * UPDATE only — tidak insert, tidak menyentuh tabel kalibrasi_kuitansi lama.
 */

$newPos = [
    // [elemen_key, x_mm, y_mm, max_width_mm|null]
    ['no_bku',               161,  14, null],
    ['no_program',           161,  22, null],
    ['no_kegiatan',          190,  22, null],
    ['terima_dari',           47,  43, null],
    ['jumlah_terbilang',      48,  49, null],
    ['uraian',                37,  72, 170],
    ['terbilang_rp',          45,  96, null],
    ['tempat_tanggal',       125, 114, null],
    ['ttd_kpa_jabatan',        47, 122, null],
    ['ttd_kpa_nama',           66, 150, null],
    ['ttd_kpa_nip',            66, 156, null],
    ['ttd_bendahara_jabatan', 107, 122, null],
    ['ttd_bendahara_nama',    126, 150, null],
    ['ttd_bendahara_nip',     126, 156, null],
    ['ttd_penerima_jabatan',  168, 122, null],
    ['ttd_penerima_nama',     184, 150, null],
];

$stmt = $pdo->prepare("
    UPDATE `kalibrasi_kuitansi_elemen`
    SET `x_mm` = :x, `y_mm` = :y, `max_width_mm` = :w, `updated_by` = 'ukur-scan-ncr'
    WHERE `elemen_key` = :k
");

foreach ($newPos as [$key, $x, $y, $w]) {
    $stmt->execute([
        ':k' => $key,
        ':x' => number_format((float) $x, 2, '.', ''),
        ':y' => number_format((float) $y, 2, '.', ''),
        ':w' => $w === null ? null : number_format((float) $w, 2, '.', ''),
    ]);
}
