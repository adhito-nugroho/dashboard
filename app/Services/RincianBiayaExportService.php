<?php

declare(strict_types=1);

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Service untuk export Excel: RINCIAN BIAYA PERJALANAN DINAS & PERHITUNGAN SPPD RAMPUNG
 * Format resmi sesuai standar CDK Bojonegoro (1 halaman pas / A4 Portrait).
 */
class RincianBiayaExportService
{
    /**
     * Generate PhpSpreadsheet object untuk Rincian Biaya Perjalanan Dinas.
     */
    public function generateSpreadsheet(array $header, array $details, array $transaksi = []): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Rincian Biaya');

        // Font default seluruh sheet
        $spreadsheet->getDefaultStyle()->getFont()->setName('Arial')->setSize(10);

        // Page setup: A4 Portrait, Fit to 1 page, gridlines visible
        $sheet->setShowGridLines(true);
        $pageSetup = $sheet->getPageSetup();
        $pageSetup->setOrientation(PageSetup::ORIENTATION_PORTRAIT);
        $pageSetup->setPaperSize(PageSetup::PAPERSIZE_A4);
        $pageSetup->setFitToPage(true);
        $pageSetup->setFitToWidth(1);
        $pageSetup->setFitToHeight(1);
        $pageSetup->setHorizontalCentered(true);

        // Margin halaman (inci)
        $sheet->getPageMargins()->setTop(0.5);
        $sheet->getPageMargins()->setBottom(0.5);
        $sheet->getPageMargins()->setLeft(0.5);
        $sheet->getPageMargins()->setRight(0.5);

        // Lebar kolom A s/d F
        $sheet->getColumnDimension('A')->setWidth(5.5);
        $sheet->getColumnDimension('B')->setWidth(22);
        $sheet->getColumnDimension('C')->setWidth(16);
        $sheet->getColumnDimension('D')->setWidth(8);
        $sheet->getColumnDimension('E')->setWidth(19);
        $sheet->getColumnDimension('F')->setWidth(24);

        // Siapkan data tanggal & nomor surat
        $nomorSurat = $header['nomor_surat'] ?: ($transaksi['nomor_surat_tugas'] ?? '-');
        $tglRaw = $transaksi['tanggal_surat_tugas'] ?? ($header['tanggal_surat'] ?? ($transaksi['tanggal'] ?? date('Y-m-d')));
        $tglFormatted = $this->formatTanggalIndo($tglRaw);

        // ── 1. JUDUL ────────────────────────────────────────────────────────
        $sheet->mergeCells('A1:F1');
        $sheet->setCellValue('A1', 'RINCIAN BIAYA PERJALANAN DINAS');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(11);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getRowDimension(1)->setRowHeight(22);
        $sheet->getRowDimension(2)->setRowHeight(10);

        // ── 2. METADATA SPT ─────────────────────────────────────────────────
        $sheet->setCellValue('A3', 'Lampiran SPT Nomor');
        $sheet->setCellValue('C3', ':  ' . $nomorSurat);
        $sheet->setCellValue('A4', 'Tanggal');
        $sheet->setCellValue('C4', ':  ' . $tglFormatted);
        $sheet->getRowDimension(5)->setRowHeight(10);

        // ── 3. TABLE HEADER (Baris 6) ───────────────────────────────────────
        $sheet->setCellValue('A6', 'No.');
        $sheet->mergeCells('B6:C6');
        $sheet->setCellValue('B6', 'PERINCIAN BIAYA');
        $sheet->setCellValue('D6', 'Hari');
        $sheet->setCellValue('E6', 'JUMLAH (Rp)');
        $sheet->setCellValue('F6', 'KETERANGAN');

        $headerStyle = [
            'font'      => ['bold' => true, 'size' => 10],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical'   => Alignment::VERTICAL_CENTER,
            ],
            'borders'   => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']],
            ],
        ];
        $sheet->getStyle('A6:F6')->applyFromArray($headerStyle);
        $sheet->getRowDimension(6)->setRowHeight(22);

        // ── 4. DATA BARIS RINCIAN (Mulai baris 7) ────────────────────────────
        // Jika details kosong, buat default 4 baris standar
        if (empty($details)) {
            $details = [
                ['nama_komponen' => 'Uang Harian', 'harga_satuan' => 0, 'jumlah_hari' => 1, 'jumlah' => 0, 'keterangan' => ''],
                ['nama_komponen' => 'BBM',         'harga_satuan' => 0, 'jumlah_hari' => null, 'jumlah' => 0, 'keterangan' => ''],
                ['nama_komponen' => 'Tol',         'harga_satuan' => 0, 'jumlah_hari' => null, 'jumlah' => 0, 'keterangan' => ''],
                ['nama_komponen' => 'Hotel',       'harga_satuan' => 0, 'jumlah_hari' => null, 'jumlah' => 0, 'keterangan' => ''],
            ];
        }

        $row = 7;
        $startRow = $row;
        $totalBiaya = 0.0;
        $accFormat = '_("Rp "* #,##0_);_("Rp "* (#,##0);_("Rp "* "-"_);_(@_)';

        foreach ($details as $idx => $d) {
            $namaKomponen = trim((string) ($d['nama_komponen'] ?? ''));
            $hargaSatuan  = (float) ($d['harga_satuan'] ?? 0);
            $jumlahHari   = $d['jumlah_hari'] !== null && $d['jumlah_hari'] !== '' ? (float) $d['jumlah_hari'] : null;
            $jumlah       = (float) ($d['jumlah'] ?? 0);
            $keterangan   = trim((string) ($d['keterangan'] ?? ''));

            $totalBiaya += $jumlah;

            // Kolom A: Nomor hanya di baris pertama paket perjalanan dinas (1)
            $sheet->setCellValue('A' . $row, $idx === 0 ? 1 : '');
            $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            // Kolom B: Nama komponen
            $sheet->setCellValue('B' . $row, $namaKomponen);
            $sheet->getStyle('B' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

            // Kolom C: Format rate (@ Rp 300.000 atau Rp 200.000)
            if ($hargaSatuan > 0) {
                $prefix = ($jumlahHari !== null && $jumlahHari > 0) ? '@ Rp' : 'Rp';
                $sheet->setCellValue('C' . $row, $prefix . '   ' . number_format($hargaSatuan, 0, ',', '.'));
            } else {
                $sheet->setCellValue('C' . $row, '');
            }
            $sheet->getStyle('C' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

            // Kolom D: Hari
            if ($jumlahHari !== null && $jumlahHari > 0) {
                $sheet->setCellValue('D' . $row, $jumlahHari);
            } else {
                $sheet->setCellValue('D' . $row, '');
            }
            $sheet->getStyle('D' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            // Kolom E: Jumlah Rp
            $sheet->setCellValue('E' . $row, $jumlah);
            $sheet->getStyle('E' . $row)->getNumberFormat()->setFormatCode($accFormat);
            $sheet->getStyle('E' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

            // Kolom F: Keterangan
            $sheet->setCellValue('F' . $row, $keterangan);
            $sheet->getStyle('F' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

            $sheet->getRowDimension($row)->setRowHeight(20);

            // Border vertikal kolom data (tanpa garis horizontal antar baris agar seperti form resmi)
            $borderDataRow = [
                'borders' => [
                    'left'  => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']],
                    'right' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']],
                ]
            ];
            $sheet->getStyle('A' . $row)->applyFromArray($borderDataRow);
            // Antara B & C tidak ada garis vertikal pemisah (merupakan satu kesatuan Perincian Biaya)
            $sheet->getStyle('C' . $row)->applyFromArray([
                'borders' => ['right' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]]
            ]);
            $sheet->getStyle('D' . $row)->applyFromArray($borderDataRow);
            $sheet->getStyle('E' . $row)->applyFromArray($borderDataRow);
            $sheet->getStyle('F' . $row)->applyFromArray($borderDataRow);

            $row++;
        }

        // ── 5. BARIS TOTAL / JUMLAH ─────────────────────────────────────────
        $totalRow = $row;
        $sheet->mergeCells('A' . $totalRow . ':D' . $totalRow);
        $sheet->setCellValue('A' . $totalRow, 'JUMLAH');
        $sheet->setCellValue('E' . $totalRow, "=SUM(E{$startRow}:E" . ($totalRow - 1) . ')');
        $sheet->setCellValue('F' . $totalRow, '');

        $sheet->getStyle('A' . $totalRow)->getFont()->setBold(true);
        $sheet->getStyle('A' . $totalRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('E' . $totalRow)->getFont()->setBold(true);
        $sheet->getStyle('E' . $totalRow)->getNumberFormat()->setFormatCode($accFormat);
        $sheet->getStyle('E' . $totalRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getRowDimension($totalRow)->setRowHeight(20);

        // Border baris total
        $sheet->getStyle('A' . $totalRow . ':F' . $totalRow)->applyFromArray([
            'borders' => [
                'top'    => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']],
                'bottom' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']],
            ]
        ]);
        $sheet->getStyle('A' . $totalRow)->applyFromArray(['borders' => ['left' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]]]);
        $sheet->getStyle('E' . $totalRow)->applyFromArray(['borders' => ['left' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']], 'right' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]]]);
        $sheet->getStyle('F' . $totalRow)->applyFromArray(['borders' => ['right' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]]]);

        // ── 6. TERBILANG ───────────────────────────────────────────────────
        $rowTerbilang = $totalRow + 1;
        $terbilangText = $this->terbilang($totalBiaya) . ' Rupiah';

        $sheet->setCellValue('A' . $rowTerbilang, 'Terbilang');
        $sheet->mergeCells('B' . $rowTerbilang . ':F' . $rowTerbilang);
        $sheet->setCellValue('B' . $rowTerbilang, $terbilangText);

        $sheet->getStyle('B' . $rowTerbilang)->getFont()->setItalic(true);
        $sheet->getStyle('B' . $rowTerbilang)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getRowDimension($rowTerbilang)->setRowHeight(20);

        $sheet->getStyle('A' . $rowTerbilang . ':F' . $rowTerbilang)->applyFromArray([
            'borders' => [
                'bottom' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']],
            ]
        ]);
        $sheet->getStyle('A' . $rowTerbilang)->applyFromArray(['borders' => ['left' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]]]);
        $sheet->getStyle('F' . $rowTerbilang)->applyFromArray(['borders' => ['right' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]]]);

        // ── 7. TANDA TANGAN TENGAH (Bendahara & Yang Menerima) ───────────────
        $bendaharaNama = $_ENV['BENDAHARA_NAMA'] ?? 'ADHITO NUGROHO, S.Kom.';
        $bendaharaNip  = $_ENV['BENDAHARA_NIP']  ?? '19840214 201001 1 011';
        $pegawaiNama   = $header['pegawai_nama'] ?? ($transaksi['nama_penerima'] ?? '-');
        $pegawaiNip    = $header['pegawai_nip'] ?? ($transaksi['pegawai_nip'] ?? '-');
        $nipPenerimaFmt = $this->formatNip($pegawaiNip);

        // Baris kosong
        $r = $rowTerbilang + 1;
        $sheet->getRowDimension($r)->setRowHeight(10);
        $this->applyOuterBoxVerticalBorders($sheet, $r);

        // Bojonegoro,
        $r++;
        $sheet->setCellValue('D' . $r, 'Bojonegoro,');
        $this->applyOuterBoxVerticalBorders($sheet, $r);

        // Telah dibayar sejumlah & Telah menerima uang sejumlah
        $r++;
        $sheet->setCellValue('A' . $r, 'Telah dibayar sejumlah');
        $sheet->setCellValue('D' . $r, 'Telah menerima uang sejumlah');
        $this->applyOuterBoxVerticalBorders($sheet, $r);

        // Nominal Rp
        $r++;
        $sheet->setCellValue('A' . $r, 'Rp');
        $sheet->setCellValue('B' . $r, $totalBiaya);
        $sheet->getStyle('B' . $r)->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getStyle('B' . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        $sheet->setCellValue('D' . $r, 'Rp');
        $sheet->setCellValue('F' . $r, $totalBiaya);
        $sheet->getStyle('F' . $r)->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getStyle('F' . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $this->applyOuterBoxVerticalBorders($sheet, $r);

        // Jabatan Penandatangan
        $r++;
        $sheet->setCellValue('A' . $r, 'Bendahara Pengeluaran Pembantu');
        $sheet->setCellValue('D' . $r, 'Yang Menerima,');
        $this->applyOuterBoxVerticalBorders($sheet, $r);

        // Ruang tanda tangan
        $r++;
        $sheet->getRowDimension($r)->setRowHeight(18);
        $this->applyOuterBoxVerticalBorders($sheet, $r);
        $r++;
        $sheet->getRowDimension($r)->setRowHeight(18);
        $this->applyOuterBoxVerticalBorders($sheet, $r);

        // Nama Penandatangan (Underline)
        $r++;
        $sheet->setCellValue('A' . $r, $bendaharaNama);
        $sheet->getStyle('A' . $r)->getFont()->setUnderline(true);
        $sheet->setCellValue('D' . $r, $pegawaiNama);
        $sheet->getStyle('D' . $r)->getFont()->setUnderline(true);
        $this->applyOuterBoxVerticalBorders($sheet, $r);

        // NIP
        $r++;
        $sheet->setCellValue('A' . $r, 'NIP. ' . $this->formatNip($bendaharaNip));
        $sheet->setCellValue('D' . $r, 'NIP. ' . $nipPenerimaFmt);
        $this->applyOuterBoxVerticalBorders($sheet, $r);

        // Garis pemisah horizontal penuh antara bagian tengah dan SPPD Rampung
        $r++;
        $sheet->getRowDimension($r)->setRowHeight(8);
        $sheet->getStyle('A' . $r . ':F' . $r)->applyFromArray([
            'borders' => [
                'bottom' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']],
            ]
        ]);
        $sheet->getStyle('A' . $r)->applyFromArray(['borders' => ['left' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]]]);
        $sheet->getStyle('F' . $r)->applyFromArray(['borders' => ['right' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]]]);

        // ── 8. PERHITUNGAN SPPD RAMPUNG ─────────────────────────────────────
        $ditetapkan = (float) ($header['ditetapkan_sejumlah'] ?? $totalBiaya);
        $dibayar    = (float) ($header['dibayar_semula']      ?? $totalBiaya);
        $sisa       = $ditetapkan - $dibayar;
        $kpaNama    = $_ENV['KPA_NAMA'] ?? 'ENDANG HANDAYANI, S.P., M.Si.';
        $kpaNip     = $_ENV['KPA_NIP']  ?? '19760328 200003 2 003';

        // Judul Rampung
        $r++;
        $sheet->getRowDimension($r)->setRowHeight(10);
        $this->applyOuterBoxVerticalBorders($sheet, $r);

        $r++;
        $sheet->mergeCells('A' . $r . ':F' . $r);
        $sheet->setCellValue('A' . $r, 'PERHITUNGAN SPPD RAMPUNG');
        $sheet->getStyle('A' . $r)->getFont()->setBold(true);
        $sheet->getStyle('A' . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getRowDimension($r)->setRowHeight(20);
        $this->applyOuterBoxVerticalBorders($sheet, $r);

        $r++;
        $sheet->getRowDimension($r)->setRowHeight(8);
        $this->applyOuterBoxVerticalBorders($sheet, $r);

        // Ditetapkan sejumlah
        $r++;
        $sheet->setCellValue('A' . $r, 'Ditetapkan sejumlah');
        $sheet->setCellValue('B' . $r, ':');
        $sheet->setCellValue('C' . $r, 'Rp');
        $sheet->setCellValue('D' . $r, $ditetapkan);
        $sheet->getStyle('D' . $r)->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getStyle('D' . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $this->applyOuterBoxVerticalBorders($sheet, $r);

        // Yang telah dibayar semula
        $r++;
        $sheet->setCellValue('A' . $r, 'Yang telah dibayar semula');
        $sheet->setCellValue('B' . $r, ':');
        $sheet->setCellValue('C' . $r, 'Rp');
        $sheet->setCellValue('D' . $r, $dibayar);
        $sheet->getStyle('D' . $r)->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getStyle('D' . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $this->applyOuterBoxVerticalBorders($sheet, $r);

        // Sisa kurang/lebih
        $r++;
        $sheet->setCellValue('A' . $r, 'Sisa kurang/lebih');
        $sheet->setCellValue('B' . $r, ':');
        $sheet->setCellValue('C' . $r, 'Rp');
        if ($sisa == 0) {
            $sheet->setCellValue('D' . $r, '-');
            $sheet->getStyle('D' . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        } else {
            $sheet->setCellValue('D' . $r, $sisa);
            $sheet->getStyle('D' . $r)->getNumberFormat()->setFormatCode('#,##0');
            $sheet->getStyle('D' . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }
        $this->applyOuterBoxVerticalBorders($sheet, $r);

        // Spasi sebelum TTD KPA
        $r++;
        $this->applyOuterBoxVerticalBorders($sheet, $r);

        // Kuasa Pengguna Anggaran
        $r++;
        $sheet->mergeCells('D' . $r . ':F' . $r);
        $sheet->setCellValue('D' . $r, 'Kuasa Pengguna Anggaran');
        $sheet->getStyle('D' . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $this->applyOuterBoxVerticalBorders($sheet, $r);

        // Ruang tanda tangan KPA
        $r++;
        $sheet->getRowDimension($r)->setRowHeight(18);
        $this->applyOuterBoxVerticalBorders($sheet, $r);
        $r++;
        $sheet->getRowDimension($r)->setRowHeight(18);
        $this->applyOuterBoxVerticalBorders($sheet, $r);

        // Nama KPA (Underline)
        $r++;
        $sheet->mergeCells('D' . $r . ':F' . $r);
        $sheet->setCellValue('D' . $r, $kpaNama);
        $sheet->getStyle('D' . $r)->getFont()->setUnderline(true);
        $sheet->getStyle('D' . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $this->applyOuterBoxVerticalBorders($sheet, $r);

        // NIP KPA
        $r++;
        $sheet->mergeCells('D' . $r . ':F' . $r);
        $sheet->setCellValue('D' . $r, 'NIP. ' . $this->formatNip($kpaNip));
        $sheet->getStyle('D' . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $this->applyOuterBoxVerticalBorders($sheet, $r);

        // Garis penutup bawah kotak
        $r++;
        $sheet->getRowDimension($r)->setRowHeight(6);
        $sheet->getStyle('A' . $r . ':F' . $r)->applyFromArray([
            'borders' => [
                'bottom' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']],
            ]
        ]);
        $sheet->getStyle('A' . $r)->applyFromArray(['borders' => ['left' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]]]);
        $sheet->getStyle('F' . $r)->applyFromArray(['borders' => ['right' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]]]);

        return $spreadsheet;
    }

    /**
     * Download spreadsheet langsung ke browser.
     */
    public function download(array $header, array $details, array $transaksi = []): void
    {
        $spreadsheet = $this->generateSpreadsheet($header, $details, $transaksi);

        $pegawaiNama = $header['pegawai_nama'] ?? ($transaksi['nama_penerima'] ?? 'Pegawai');
        $safeName    = preg_replace('/[^A-Za-z0-9]/', '_', $pegawaiNama);
        $bulanTahun  = date('F_Y', strtotime($transaksi['tanggal'] ?? date('Y-m-d')));
        $filename    = 'Rincian_Biaya_' . $safeName . '_' . $bulanTahun . '.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        header('Pragma: public');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    /**
     * Berikan border samping (kiri A dan kanan F) pada baris section berbingkai.
     */
    private function applyOuterBoxVerticalBorders(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, int $row): void
    {
        $sheet->getStyle('A' . $row)->applyFromArray([
            'borders' => ['left' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]]
        ]);
        $sheet->getStyle('F' . $row)->applyFromArray([
            'borders' => ['right' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]]
        ]);
    }

    /**
     * Format NIP 18 digit standar PNS: 19840214 201001 1 011
     */
    public function formatNip(?string $nip): string
    {
        if (!$nip) return '-';
        $clean = preg_replace('/[^0-9]/', '', $nip);
        if (strlen($clean) === 18) {
            return substr($clean, 0, 8) . ' ' . substr($clean, 8, 6) . ' ' . substr($clean, 14, 1) . ' ' . substr($clean, 15, 3);
        }
        return $nip;
    }

    /**
     * Format Tanggal Bahasa Indonesia: 21 April 2026
     */
    public function formatTanggalIndo(?string $dateStr): string
    {
        if (!$dateStr) return date('d F Y');
        $t = strtotime($dateStr);
        if (!$t) return $dateStr;

        $bulan = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
        ];

        $d = date('j', $t);
        $m = (int) date('n', $t);
        $y = date('Y', $t);

        return $d . ' ' . ($bulan[$m] ?? date('F', $t)) . ' ' . $y;
    }

    /**
     * Konversi angka ke kalimat terbilang bahasa Indonesia.
     */
    public function terbilang(float $n): string
    {
        $n = abs((int) round($n));
        if ($n === 0) return 'Nol';

        $satuan = ['', 'Satu', 'Dua', 'Tiga', 'Empat', 'Lima', 'Enam', 'Tujuh', 'Delapan', 'Sembilan', 'Sepuluh', 'Sebelas'];

        if ($n < 12) {
            return $satuan[$n];
        }
        if ($n < 20) {
            return $this->terbilang($n - 10) . ' Belas';
        }
        if ($n < 100) {
            $sisa = $n % 10;
            return $this->terbilang((int) ($n / 10)) . ' Puluh' . ($sisa ? ' ' . $this->terbilang($sisa) : '');
        }
        if ($n < 200) {
            $sisa = $n - 100;
            return 'Seratus' . ($sisa ? ' ' . $this->terbilang($sisa) : '');
        }
        if ($n < 1000) {
            $sisa = $n % 100;
            return $this->terbilang((int) ($n / 100)) . ' Ratus' . ($sisa ? ' ' . $this->terbilang($sisa) : '');
        }
        if ($n < 2000) {
            $sisa = $n - 1000;
            return 'Seribu' . ($sisa ? ' ' . $this->terbilang($sisa) : '');
        }
        if ($n < 1000000) {
            $sisa = $n % 1000;
            return $this->terbilang((int) ($n / 1000)) . ' Ribu' . ($sisa ? ' ' . $this->terbilang($sisa) : '');
        }
        if ($n < 1000000000) {
            $sisa = $n % 1000000;
            return $this->terbilang((int) ($n / 1000000)) . ' Juta' . ($sisa ? ' ' . $this->terbilang($sisa) : '');
        }
        if ($n < 1000000000000) {
            $sisa = $n % 1000000000;
            return $this->terbilang((int) ($n / 1000000000)) . ' Miliar' . ($sisa ? ' ' . $this->terbilang($sisa) : '');
        }
        return (string) $n;
    }
}
