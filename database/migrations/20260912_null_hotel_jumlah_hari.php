<?php

declare(strict_types=1);

/**
 * Migration: kosongkan jumlah_hari pada komponen Hotel di rincian biaya
 * perjalanan dinas (termasuk varian nama seperti "Penginapan / Hotel").
 * Hotel bukan komponen harian; kolom Hari harus kosong di output.
 * Kolom `jumlah` tidak diubah sehingga total tetap sama.
 */

$pdo->exec("
    UPDATE `rincian_biaya_perjalanan_dinas_detail`
    SET `jumlah_hari` = NULL
    WHERE LOWER(`nama_komponen`) LIKE '%hotel%'
      AND `jumlah_hari` IS NOT NULL
");
