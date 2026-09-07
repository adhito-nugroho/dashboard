<?php

declare(strict_types=1);

/**
 * Helper terbilang Bahasa Indonesia untuk kuitansi.
 * Diekstrak dari App\Services\RincianBiayaExportService::terbilang() agar bisa dipakai
 * di generator PDF kuitansi tanpa dependensi ke service Excel (fitur BKU tidak disentuh).
 */

if (!function_exists('kuitansi_terbilang')) {
    function kuitansi_terbilang(float|int $n): string
    {
        $n = abs((int) round((float) $n));
        if ($n === 0) {
            return 'Nol';
        }

        $satuan = ['', 'Satu', 'Dua', 'Tiga', 'Empat', 'Lima', 'Enam', 'Tujuh', 'Delapan', 'Sembilan', 'Sepuluh', 'Sebelas'];

        if ($n < 12) {
            return $satuan[$n];
        }
        if ($n < 20) {
            return kuitansi_terbilang($n - 10) . ' Belas';
        }
        if ($n < 100) {
            $sisa = $n % 10;
            return kuitansi_terbilang((int) ($n / 10)) . ' Puluh' . ($sisa ? ' ' . kuitansi_terbilang($sisa) : '');
        }
        if ($n < 200) {
            $sisa = $n - 100;
            return 'Seratus' . ($sisa ? ' ' . kuitansi_terbilang($sisa) : '');
        }
        if ($n < 1000) {
            $sisa = $n % 100;
            return kuitansi_terbilang((int) ($n / 100)) . ' Ratus' . ($sisa ? ' ' . kuitansi_terbilang($sisa) : '');
        }
        if ($n < 2000) {
            $sisa = $n - 1000;
            return 'Seribu' . ($sisa ? ' ' . kuitansi_terbilang($sisa) : '');
        }
        if ($n < 1000000) {
            $sisa = $n % 1000;
            return kuitansi_terbilang((int) ($n / 1000)) . ' Ribu' . ($sisa ? ' ' . kuitansi_terbilang($sisa) : '');
        }
        if ($n < 1000000000) {
            $sisa = $n % 1000000;
            return kuitansi_terbilang((int) ($n / 1000000)) . ' Juta' . ($sisa ? ' ' . kuitansi_terbilang($sisa) : '');
        }
        if ($n < 1000000000000) {
            $sisa = $n % 1000000000;
            return kuitansi_terbilang((int) ($n / 1000000000)) . ' Miliar' . ($sisa ? ' ' . kuitansi_terbilang($sisa) : '');
        }
        return (string) $n;
    }
}

if (!function_exists('kuitansi_tanggal_id')) {
    /**
     * Format "27 Agustus 2026" dari YYYY-MM-DD.
     */
    function kuitansi_tanggal_id(string $ymd): string
    {
        $bulan = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];
        $t = strtotime($ymd);
        if ($t === false) {
            return $ymd;
        }
        return ((int) date('j', $t)) . ' ' . ($bulan[(int) date('n', $t)] ?? date('F', $t)) . ' ' . date('Y', $t);
    }
}

if (!function_exists('kuitansi_pdf_text')) {
    /**
     * FPDF core font (Helvetica/Arial) hanya mendukung Latin-1 — transliterasi dari UTF-8.
     */
    function kuitansi_pdf_text(string $s): string
    {
        $out = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $s);
        return $out === false ? $s : $out;
    }
}
