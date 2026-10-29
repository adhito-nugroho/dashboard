<?php

declare(strict_types=1);

/**
 * Koordinat cetak ISI lembar 2 SPPD (overlay di atas blanko yang sudah tercetak).
 *
 * Cara baca:
 * - Semua posisi elemen diukur pada SISTEM KOORDINAT TEMPLATE (scan lembar 2,
 *   197,2 x 283,3 mm, origin = pojok kiri atas template), bukan langsung A4.
 * - Saat dicetak, posisi digeser 'offset_mm' agar template duduk di tengah A4
 *   (210 x 297): x = (210 - 197,2) / 2 = 6,4 ; y = (297 - 283,3) / 2 = 6,85.
 * - Bila hasil cetak bergeser, cukup ubah 'offset_mm' (menggeser SEMUA elemen)
 *   atau x_mm/y_mm elemen terkait. Bandingkan dengan "Cetak Uji Posisi".
 * - x_mm/y_mm = pojok kiri-atas kotak teks; h_mm = tinggi kotak (teks
 *   tercetak di tengah kotak, seperti Cell() FPDF).
 * - max_lines = jumlah baris maksimum teks (default 1). Bila > 1, teks dipecah
 *   per kata dan baris berikutnya turun sebesar h_mm; dipakai untuk jabatan
 *   pejabat yang panjang.
 */

return [
    'page' => [
        'width_mm' => 210.0,
        'height_mm' => 297.0,
    ],

    'content' => [
        'width_mm' => 197.2,
        'height_mm' => 283.3,
        'border_mm' => ['left' => 2.0, 'top' => 1.6, 'right' => 195.5, 'bottom' => 250.7],
    ],

    'offset_mm' => [
        'x' => 6.4,
        'y' => 6.85,
    ],

    'font' => [
        'family' => 'Helvetica',
        'size' => 10.5,
    ],

    'elements' => [
        // ── Kolom kanan (baris I): keberangkatan dari tempat kedudukan ──
        'i_berangkat_dari' => [
            'x_mm' => 139.0, 'y_mm' => 0.0, 'w_mm' => 54.0, 'h_mm' => 5.0,
            'align' => 'L', 'style' => '', 'label' => 'I - Berangkat dari (tempat kedudukan)',
        ],
        'i_ke' => [
            'x_mm' => 139.0, 'y_mm' => 8.1, 'w_mm' => 54.0, 'h_mm' => 5.0,
            'align' => 'L', 'style' => '', 'label' => 'I - Ke',
        ],
        'i_tanggal' => [
            'x_mm' => 139.0, 'y_mm' => 12.4, 'w_mm' => 54.0, 'h_mm' => 5.0,
            'align' => 'L', 'style' => '', 'label' => 'I - Pada tanggal',
        ],

        // ── Kolom kiri (baris II): kedatangan di kota tujuan ──
        'tiba_di' => [
            'x_mm' => 38.0, 'y_mm' => 43.0, 'w_mm' => 54.0, 'h_mm' => 5.0,
            'align' => 'L', 'style' => '', 'label' => 'II - Tiba di',
        ],
        'tiba_tanggal' => [
            'x_mm' => 38.0, 'y_mm' => 47.1, 'w_mm' => 54.0, 'h_mm' => 5.0,
            'align' => 'L', 'style' => '', 'label' => 'II - Tiba di: pada tanggal',
        ],
        'pejabat_tiba_jabatan' => [
            'x_mm' => 4.0, 'y_mm' => 53.9, 'w_mm' => 60.0, 'h_mm' => 5.0,
            'align' => 'C', 'style' => '', 'max_lines' => 2, 'label' => 'II - jabatan pejabat tujuan (tiba)',
        ],
        'pejabat_tiba_nama' => [
            'x_mm' => 4.0, 'y_mm' => 68.9, 'w_mm' => 60.0, 'h_mm' => 5.0,
            'align' => 'L', 'style' => 'U', 'label' => 'II - nama pejabat tujuan (tiba)',
        ],
        'pejabat_tiba_nip' => [
            'x_mm' => 4.0, 'y_mm' => 73.0, 'w_mm' => 60.0, 'h_mm' => 5.0,
            'align' => 'L', 'style' => '', 'label' => 'II - NIP pejabat tujuan (tiba)',
        ],

        // ── Kolom kanan (baris II): keberangkatan kembali dari kota tujuan ──
        'berangkat_dari' => [
            'x_mm' => 139.0, 'y_mm' => 43.0, 'w_mm' => 54.0, 'h_mm' => 5.0,
            'align' => 'L', 'style' => '', 'label' => 'II - Berangkat dari',
        ],
        'berangkat_ke' => [
            'x_mm' => 139.0, 'y_mm' => 47.1, 'w_mm' => 54.0, 'h_mm' => 5.0,
            'align' => 'L', 'style' => '', 'label' => 'II - ke',
        ],
        'berangkat_tanggal' => [
            'x_mm' => 139.0, 'y_mm' => 51.1, 'w_mm' => 54.0, 'h_mm' => 5.0,
            'align' => 'L', 'style' => '', 'label' => 'II - Berangkat dari: pada tanggal',
        ],
        'pejabat_berangkat_jabatan' => [
            'x_mm' => 98.0, 'y_mm' => 56.4, 'w_mm' => 54.0, 'h_mm' => 5.0,
            'align' => 'C', 'style' => '', 'max_lines' => 2, 'label' => 'II - jabatan pejabat tujuan (berangkat)',
        ],
        'pejabat_berangkat_nama' => [
            'x_mm' => 98.0, 'y_mm' => 68.9, 'w_mm' => 54.0, 'h_mm' => 5.0,
            'align' => 'L', 'style' => 'U', 'label' => 'II - nama pejabat tujuan (berangkat)',
        ],
        'pejabat_berangkat_nip' => [
            'x_mm' => 98.0, 'y_mm' => 73.0, 'w_mm' => 54.0, 'h_mm' => 5.0,
            'align' => 'L', 'style' => '', 'label' => 'II - NIP pejabat tujuan (berangkat)',
        ],

        // ── Kolom kiri (baris VI): tiba kembali di tempat kedudukan ──
        'vi_tiba_di' => [
            'x_mm' => 38.0, 'y_mm' => 207.3, 'w_mm' => 54.0, 'h_mm' => 5.0,
            'align' => 'L', 'style' => '', 'label' => 'VI - Tiba di',
        ],
        'vi_tiba_tanggal' => [
            'x_mm' => 38.0, 'y_mm' => 212.1, 'w_mm' => 54.0, 'h_mm' => 5.0,
            'align' => 'L', 'style' => '', 'label' => 'VI - Pada tanggal',
        ],
    ],
];
