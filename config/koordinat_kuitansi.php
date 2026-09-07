<?php

declare(strict_types=1);

/**
 * Koordinat dasar elemen kuitansi pada kertas NCR 215mm (lebar) x 165mm (tinggi), landscape.
 *
 * SATU-SATUNYA tempat definisi posisi. Jangan hardcode koordinat di service/controller.
 * Saat generate PDF: posisi_akhir = koordinat_dasar + offset kalibrasi (offset_x_mm, offset_y_mm).
 *
 * Origin (0,0) = pojok kiri atas kertas. Satuan milimeter (mm).
 *
 * ESTIMASI AWAL berbasis proporsi kuitansi NCR umum — WAJIB divalidasi lewat
 * halaman "Kalibrasi Cetak Kuitansi" > tombol "Cetak Uji" sebelum dipakai produksi.
 * Cara kalibrasi: cetak uji di atas kertas NCR contoh, ukur selisih (mm), isi offset, simpan.
 */

return [
    // Ukuran halaman (landscape: lebar x tinggi)
    'page' => [
        'width_mm'  => 215,
        'height_mm' => 165,
        'orientation' => 'L',
    ],

    // Kotak kanan atas (No. BKU/HAL, No. Program/Kegiatan, No. Kegiatan)
    'no_bku' => [
        'x_mm' => 150, 'y_mm' => 12, 'w_mm' => 55, 'h_mm' => 6,
        'font' => 'Times', 'style' => '', 'size' => 11, 'align' => 'L',
        'label' => 'No. BKU/HAL',
    ],
    'no_program' => [
        'x_mm' => 150, 'y_mm' => 19, 'w_mm' => 55, 'h_mm' => 6,
        'font' => 'Times', 'style' => '', 'size' => 11, 'align' => 'L',
        'label' => 'No. Program/Kegiatan',
    ],
    'no_kegiatan' => [
        'x_mm' => 150, 'y_mm' => 26, 'w_mm' => 55, 'h_mm' => 6,
        'font' => 'Times', 'style' => '', 'size' => 11, 'align' => 'L',
        'label' => 'No. Kegiatan',
    ],

    // "Terima dari" (boleh kosong — service mencetak string kosong jika tidak dipakai)
    'terima_dari' => [
        'x_mm' => 38, 'y_mm' => 42, 'w_mm' => 162, 'h_mm' => 6,
        'font' => 'Times', 'style' => '', 'size' => 11, 'align' => 'L',
        'label' => 'Terima dari',
    ],

    // "Jumlah: <terbilang> Rupiah" (satu baris, huruf kapital tiap kata)
    'jumlah_terbilang' => [
        'x_mm' => 38, 'y_mm' => 52, 'w_mm' => 162, 'h_mm' => 6,
        'font' => 'Times', 'style' => 'I', 'size' => 11, 'align' => 'L',
        'label' => 'Jumlah (terbilang)',
    ],

    // "Untuk Pembayaran:" — uraian panjang, word-wrap otomatis via MultiCell (3-5 baris)
    'uraian' => [
        'x_mm' => 15, 'y_mm' => 65, 'w_mm' => 185, 'h_mm' => 6,
        'font' => 'Times', 'style' => '', 'size' => 11, 'align' => 'L',
        'line_h_mm' => 6,
        'label' => 'Untuk Pembayaran (uraian)',
    ],

    // "Terbilang Rp: <angka>" dalam kotak (rata kanan)
    'terbilang_rp' => [
        'x_mm' => 150, 'y_mm' => 100, 'w_mm' => 50, 'h_mm' => 7,
        'font' => 'Times', 'style' => 'B', 'size' => 12, 'align' => 'R',
        'label' => 'Terbilang Rp (angka)',
    ],

    // "Bojonegoro, 27 Agustus 2026" (rata kiri di area kanan bawah)
    'tempat_tanggal' => [
        'x_mm' => 125, 'y_mm' => 112, 'w_mm' => 75, 'h_mm' => 6,
        'font' => 'Times', 'style' => '', 'size' => 11, 'align' => 'L',
        'label' => 'Tempat & tanggal',
    ],

    // 3 blok tanda tangan sejajar bawah (masing-masing: jabatan, ruang TTD, nama, NIP)
    'ttd1' => [ // Setuju dibayar / Kuasa Pengguna Anggaran
        'x_mm' => 10, 'y_mm' => 118, 'w_mm' => 60,
        'font' => 'Times', 'style' => '', 'size' => 10, 'align' => 'C',
        'jabatan' => "Setuju dibayar\nKuasa Pengguna Anggaran",
        'label' => 'TTD 1 (KPA)',
    ],
    'ttd2' => [ // Lunas dibayar / Bendahara Pengeluaran Pembantu
        'x_mm' => 78, 'y_mm' => 118, 'w_mm' => 60,
        'font' => 'Times', 'style' => '', 'size' => 10, 'align' => 'C',
        'jabatan' => "Lunas dibayar, Tgl. .....\nBendahara Pengeluaran Pembantu",
        'label' => 'TTD 2 (Bendahara)',
    ],
    'ttd3' => [ // Yang menerima
        'x_mm' => 146, 'y_mm' => 118, 'w_mm' => 60,
        'font' => 'Times', 'style' => '', 'size' => 10, 'align' => 'C',
        'jabatan' => "Yang menerima",
        'label' => 'TTD 3 (Penerima)',
    ],

    // Tinggi ruang tanda tangan (jarak jabatan -> nama) dan jarak nama -> NIP
    'ttd_signature_space_mm' => 18,
    'ttd_nip_gap_mm' => 1,
];
