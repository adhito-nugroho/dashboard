<?php

declare(strict_types=1);

/**
 * Migration: hapus 3 elemen jabatan TTD dari kalibrasi_kuitansi_elemen.
 * Alasan: teks jabatan ("Setuju dibayar", "Lunas dibayar", "Yang menerima")
 * sudah pre-printed di kertas NCR — tidak digambar ulang oleh generator.
 * Yang dicetak hanya nama + NIP (13 elemen_key tersisa).
 */

$pdo->exec("
    DELETE FROM `kalibrasi_kuitansi_elemen`
    WHERE `elemen_key` IN ('ttd_kpa_jabatan', 'ttd_bendahara_jabatan', 'ttd_penerima_jabatan')
");
