<?php

declare(strict_types=1);

/**
 * Koordinat DEFAULT elemen kuitansi pada kertas NCR 215mm (lebar) x 165mm (tinggi), landscape.
 *
 * Posisi AKTIF tersimpan PER ELEMEN di tabel kalibrasi_kuitansi_elemen
 * (16 elemen_key, lihat KuitansiPdfService::ELEMEN_KEYS) dan di-inject via
 * KuitansiPdfService::setPositions(). File ini hanya fallback + definisi
 * font/ukuran halaman/teks jabatan. Tabel kalibrasi_kuitansi lama (offset
 * global) tidak dipakai lagi oleh generator (disimpan untuk histori).
 *
 * Origin (0,0) = pojok kiri atas kertas. Satuan milimeter (mm).
 */

return [
    // Ukuran halaman (landscape: lebar x tinggi)
    'page' => [
        'width_mm'  => 215,
        'height_mm' => 165,
        'orientation' => 'L',
    ],

    // Kotak kanan atas: baris 1 NO. BKU/HAL, baris 2 NO. PROGRAM/NO. KEGIATAN
    // (satu sel nilai berisi "/": program di kiri slash, kegiatan di kanan).
    // Diukur dari scan NCR asli (215x165mm).
    'no_bku' => [
        'x_mm' => 161, 'y_mm' => 14, 'w_mm' => 45, 'h_mm' => 6,
        'font' => 'Times', 'style' => '', 'size' => 11, 'align' => 'L',
        'label' => 'No. BKU/HAL',
    ],
    'no_program' => [
        'x_mm' => 161, 'y_mm' => 22, 'w_mm' => 27, 'h_mm' => 6,
        'font' => 'Times', 'style' => '', 'size' => 11, 'align' => 'L',
        'label' => 'No. Program/Kegiatan',
    ],
    'no_kegiatan' => [
        'x_mm' => 190, 'y_mm' => 22, 'w_mm' => 14, 'h_mm' => 6,
        'font' => 'Times', 'style' => '', 'size' => 11, 'align' => 'L',
        'label' => 'No. Kegiatan',
    ],

    // "Terima dari" (pre-printed "KEPALA DINAS..."; value default kosong)
    'terima_dari' => [
        'x_mm' => 47, 'y_mm' => 43, 'w_mm' => 158, 'h_mm' => 6,
        'font' => 'Times', 'style' => '', 'size' => 11, 'align' => 'L',
        'label' => 'Terima dari',
    ],

    // "Jumlah: <terbilang> Rupiah" — di dalam box miring pertama
    'jumlah_terbilang' => [
        'x_mm' => 48, 'y_mm' => 49, 'w_mm' => 155, 'h_mm' => 6,
        'font' => 'Times', 'style' => 'I', 'size' => 11, 'align' => 'L',
        'label' => 'Jumlah (terbilang)',
    ],

    // "Untuk Pembayaran:" — area kosong di bawah label sampai garis pemisah
    'uraian' => [
        'x_mm' => 37, 'y_mm' => 72, 'w_mm' => 170, 'h_mm' => 6,
        'font' => 'Times', 'style' => '', 'size' => 11, 'align' => 'L',
        'line_h_mm' => 6,
        'label' => 'Untuk Pembayaran (uraian)',
    ],

    // Box angka "Terbilang Rp." — di KIRI bawah (bukan kanan)
    'terbilang_rp' => [
        'x_mm' => 45, 'y_mm' => 96, 'w_mm' => 65, 'h_mm' => 7,
        'font' => 'Times', 'style' => 'B', 'size' => 12, 'align' => 'R',
        'label' => 'Terbilang Rp (angka)',
    ],

    // "Bojonegoro, ..." — di atas garis titik-titik kanan
    'tempat_tanggal' => [
        'x_mm' => 125, 'y_mm' => 114, 'w_mm' => 78, 'h_mm' => 6,
        'font' => 'Times', 'style' => '', 'size' => 11, 'align' => 'L',
        'label' => 'Tempat & tanggal',
    ],

    // 3 kolom tanda tangan (diukur dari scan): jabatan y=122,
    // nama tepat di garis titik-titik y=150, NIP di bawahnya y=156.
    'ttd1' => [ // Setuju dibayar / Kuasa Pengguna Anggaran
        'x_mm' => 47, 'y_mm' => 122, 'w_mm' => 60,
        'font' => 'Times', 'style' => '', 'size' => 10, 'align' => 'C',
        'jabatan' => "Setuju dibayar\nKuasa Pengguna Anggaran",
        'label' => 'TTD 1 (KPA)',
    ],
    'ttd2' => [ // Lunas dibayar / Bendahara Pengeluaran Pembantu
        'x_mm' => 107, 'y_mm' => 122, 'w_mm' => 60,
        'font' => 'Times', 'style' => '', 'size' => 10, 'align' => 'C',
        'jabatan' => "Lunas dibayar, Tgl. .....\nBendahara Pengeluaran Pembantu",
        'label' => 'TTD 2 (Bendahara)',
    ],
    'ttd3' => [ // Yang menerima
        'x_mm' => 168, 'y_mm' => 122, 'w_mm' => 40,
        'font' => 'Times', 'style' => '', 'size' => 10, 'align' => 'C',
        'jabatan' => "Yang menerima",
        'label' => 'TTD 3 (Penerima)',
    ],

    // Lebar tiap baris TTD (dipakai service + kanvas; nama di atas garis titik-titik)
    'ttd_widths' => [
        'ttd_kpa_jabatan' => 60, 'ttd_kpa_nama' => 47, 'ttd_kpa_nip' => 47,
        'ttd_bendahara_jabatan' => 60, 'ttd_bendahara_nama' => 46, 'ttd_bendahara_nip' => 46,
        'ttd_penerima_jabatan' => 40, 'ttd_penerima_nama' => 28,
    ],

    // Tinggi ruang tanda tangan (jarak jabatan -> nama) dan jarak nama -> NIP
    'ttd_signature_space_mm' => 18,
    'ttd_nip_gap_mm' => 1,
];
