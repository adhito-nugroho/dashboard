<?php

declare(strict_types=1);

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Export lengkap modul Tambah Uang (TU).
 *
 * Sheet 1: ringkasan KPI + rekap per status.
 * Sheet 2: satu baris per pengajuan TU (kebutuhan, SP2D, terpakai, setoran, sisa).
 * Sheet 3: rincian rekening per pengajuan.
 * Sheet 4: riwayat pembayaran/transaksi yang dibayar dari dana TU.
 */
class PengajuanTuExportService
{
    private const SHEET_SUMMARY = 'Ringkasan';
    private const SHEET_LIST = 'Rekap Pengajuan';
    private const SHEET_REKENING = 'Detail Rekening';
    private const SHEET_PAYMENT = 'Riwayat Pembayaran';

    private const HEADER_ROW = 6;
    private const FIRST_DATA_ROW = 7;

    private const LIST_LAST_COL = 'R';
    private const REKENING_LAST_COL = 'J';
    private const PAYMENT_LAST_COL = 'L';

    private const NUMBER_FORMAT = '#,##0';
    private const PERCENT_FORMAT = '0.0%';
    private const SIGNED_FORMAT = '#,##0;[Red]-#,##0';

    private const NAVY = '1E3A5F';
    private const ZEBRA = 'F8FAFC';
    private const ORG_NAME = 'CABANG DINAS KEHUTANAN (CDK) WILAYAH BOJONEGORO';

    private const BULAN = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];

    private const STATUS_ORDER = ['diajukan', 'disetujui', 'cair', 'selesai', 'ditolak'];

    private const STATUS_LABEL = [
        'diajukan'  => 'Menunggu Cair',
        'disetujui' => 'Disetujui (lama)',
        'cair'      => 'SP2D Cair',
        'selesai'   => 'Selesai',
        'ditolak'   => 'Ditolak',
    ];

    private const JENIS_LABEL = [
        'belanja'          => 'Belanja',
        'perjalanan_dinas' => 'Perjalanan Dinas',
        'honorarium'       => 'Honorarium',
        'lainnya'          => 'Lainnya',
    ];

    private const TRANSAKSI_LABEL = [
        'diajukan'    => 'Menunggu',
        'diverifikasi' => 'Terverifikasi',
        'ditolak'     => 'Ditolak',
    ];

    public function download(array $report): void
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setCreator('Dashboard Anggaran CDK Wilayah Bojonegoro')
            ->setTitle('Rekap Pengajuan TU ' . (int) ($report['tahun'] ?? date('Y')))
            ->setSubject('Rekapitulasi Pengajuan Tambah Uang (TU)');

        $this->buildSummarySheet($spreadsheet->getActiveSheet(), $report);
        $this->buildListSheet($spreadsheet->createSheet(), $report);
        $this->buildRekeningSheet($spreadsheet->createSheet(), $report);
        $this->buildPaymentSheet($spreadsheet->createSheet(), $report);

        $spreadsheet->setActiveSheetIndex(0);

        $filename = 'Rekap_Pengajuan_TU_' . (int) ($report['tahun'] ?? date('Y'));
        if (!empty($report['bulan'])) {
            $filename .= '-' . str_pad((string) (int) $report['bulan'], 2, '0', STR_PAD_LEFT);
        }
        $filename .= '.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        header('Pragma: public');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    // ──────────────────────────────────────────────────────────
    // SHEET 1 — Ringkasan
    // ──────────────────────────────────────────────────────────

    private function buildSummarySheet(Worksheet $sheet, array $report): void
    {
        $sheet->setTitle(self::SHEET_SUMMARY);
        $tahun  = (int) ($report['tahun'] ?? date('Y'));
        $totals = $report['totals'] ?? [];
        $jatuhTempo = (int) ($totals['jatuh_tempo'] ?? 0);

        $this->writeTitleBlock($sheet, 'REKAPITULASI PENGAJUAN TAMBAH UANG (TU) ' . $tahun, 8, $report);

        $cardsTop = [
            ['A', 'B', 'JUMLAH PENGAJUAN', (int) ($totals['count'] ?? 0), self::NUMBER_FORMAT, 'EEF2FF', self::NAVY],
            ['C', 'D', 'TOTAL PENGAJUAN (Rp)', (float) ($totals['kebutuhan'] ?? 0), self::NUMBER_FORMAT, 'E0E7FF', '3730A3'],
            ['E', 'F', 'TOTAL CAIR / SP2D (Rp)', (float) ($totals['cair'] ?? 0), self::NUMBER_FORMAT, 'DCFCE7', '166534'],
            ['G', 'H', 'TOTAL TERPAKAI (Rp)', (float) ($totals['terpakai'] ?? 0), self::NUMBER_FORMAT, 'DBEAFE', '1D4ED8'],
        ];
        $cardsBottom = [
            ['A', 'B', 'SISA DANA TU (Rp)', (float) ($totals['sisa'] ?? 0), self::SIGNED_FORMAT, 'FEF3C7', '92400E'],
            ['C', 'D', 'TOTAL DISETOR (Rp)', (float) ($totals['disetor'] ?? 0), self::NUMBER_FORMAT, 'F1F5F9', '334155'],
            ['E', 'F', 'JATUH TEMPO SPJ', $jatuhTempo, self::NUMBER_FORMAT, $jatuhTempo > 0 ? 'FEE2E2' : 'F1F5F9', $jatuhTempo > 0 ? 'B91C1C' : '334155'],
            ['G', 'H', 'SERAPAN TERHADAP CAIR', (float) ($totals['pct_serapan'] ?? 0), self::PERCENT_FORMAT, 'F1F5F9', '334155'],
        ];

        $this->writeCards($sheet, 7, $cardsTop);
        $this->writeCards($sheet, 9, $cardsBottom);

        $sheet->mergeCells('A12:H12');
        $sheet->setCellValue('A12', 'REKAP PER STATUS PENGAJUAN');
        $sheet->getStyle('A12:H12')->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::NAVY]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(12)->setRowHeight(22);

        $headers = [
            'A' => 'STATUS',
            'B' => 'JUMLAH',
            'C' => 'KEBUTUHAN (Rp)',
            'D' => 'CAIR (Rp)',
            'E' => 'TERPAKAI (Rp)',
            'F' => 'SISA DANA (Rp)',
            'G' => 'DISETOR (Rp)',
            'H' => 'JATUH TEMPO',
        ];
        foreach ($headers as $col => $label) {
            $sheet->setCellValue($col . '13', $label);
        }
        $sheet->getStyle('A13:H13')->applyFromArray([
            'font' => ['bold' => true, 'size' => 9, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '334155']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'FFFFFF']]],
        ]);
        $sheet->getRowDimension(13)->setRowHeight(30);

        $groups = $report['statusGroups'] ?? [];
        $row = 14;
        foreach (self::STATUS_ORDER as $status) {
            $group = $groups[$status] ?? [];
            $sheet->setCellValue('A' . $row, self::STATUS_LABEL[$status]);
            $sheet->setCellValue('B' . $row, (int) ($group['count'] ?? 0));
            $sheet->setCellValue('C' . $row, (float) ($group['kebutuhan'] ?? 0));
            $sheet->setCellValue('D' . $row, (float) ($group['cair'] ?? 0));
            $sheet->setCellValue('E' . $row, (float) ($group['terpakai'] ?? 0));
            $sheet->setCellValue('F' . $row, (float) ($group['sisa'] ?? 0));
            $sheet->setCellValue('G' . $row, (float) ($group['disetor'] ?? 0));
            $sheet->setCellValue('H' . $row, (int) ($group['jatuh_tempo'] ?? 0));

            [$fontColor, $bgColor] = $this->statusColors($status);
            $sheet->getStyle('A' . $row . ':H' . $row)->applyFromArray([
                'font' => ['size' => 9, 'color' => ['rgb' => '1F2937']],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E5E7EB']]],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            ]);
            $sheet->getStyle('A' . $row)->getFont()->setBold(true);
            $sheet->getStyle('A' . $row)->getFont()->getColor()->setRGB($fontColor);
            $sheet->getStyle('A' . $row)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB($bgColor);
            $this->formatNumberCells($sheet, ['C', 'D', 'E', 'F', 'G'], $row);
            foreach (['B', 'H'] as $col) {
                $sheet->getStyle($col . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle($col . $row)->getNumberFormat()->setFormatCode(self::NUMBER_FORMAT);
            }
            $sheet->getStyle('F' . $row)->getFont()->setBold(true);
            $sisaGroup = (float) ($group['sisa'] ?? 0);
            $cairGroup = (float) ($group['cair'] ?? 0);
            $sheet->getStyle('F' . $row)->getFont()->getColor()->setRGB(
                $cairGroup <= 0.005 ? '6B7280' : ($sisaGroup > 0.005 ? '92400E' : '166534')
            );
            $sheet->getRowDimension($row)->setRowHeight(20);
            $row++;
        }

        $sheet->setCellValue('A' . $row, 'JUMLAH TOTAL');
        $sheet->setCellValue('B' . $row, (int) ($totals['count'] ?? 0));
        $sheet->setCellValue('C' . $row, (float) ($totals['kebutuhan'] ?? 0));
        $sheet->setCellValue('D' . $row, (float) ($totals['cair'] ?? 0));
        $sheet->setCellValue('E' . $row, (float) ($totals['terpakai'] ?? 0));
        $sheet->setCellValue('F' . $row, (float) ($totals['sisa'] ?? 0));
        $sheet->setCellValue('G' . $row, (float) ($totals['disetor'] ?? 0));
        $sheet->setCellValue('H' . $row, $jatuhTempo);
        $sheet->getStyle('A' . $row . ':H' . $row)->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::NAVY]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => 'FFFFFF']]],
        ]);
        $this->formatNumberCells($sheet, ['C', 'D', 'E', 'F', 'G'], $row);
        foreach (['B', 'H'] as $col) {
            $sheet->getStyle($col . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle($col . $row)->getNumberFormat()->setFormatCode(self::NUMBER_FORMAT);
        }
        $sheet->getRowDimension($row)->setRowHeight(22);

        $notes = [
            'Kebutuhan = nilai TU yang diajukan; Cair = nominal SP2D-TU yang benar-benar masuk.',
            'Terpakai = pembayaran terverifikasi yang dibayar dari dana TU; Sisa Dana = Cair − Terpakai − Disetor.',
            'Jatuh tempo SPJ = TU berstatus cair yang batas SPJ-nya sudah lewat dan belum diselesaikan.',
            'Data mengikuti filter halaman Pengajuan TU saat tombol unduh diklik (tahun, bulan, status, seksi, pencarian nomor).',
        ];
        $noteRow = $row + 2;
        foreach ($notes as $note) {
            $sheet->mergeCells('A' . $noteRow . ':H' . $noteRow);
            $sheet->setCellValue('A' . $noteRow, '• ' . $note);
            $sheet->getStyle('A' . $noteRow)->applyFromArray([
                'font' => ['size' => 8, 'italic' => true, 'color' => ['rgb' => '6B7280']],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            ]);
            $noteRow++;
        }

        foreach (['A' => 30, 'B' => 12, 'C' => 18, 'D' => 18, 'E' => 18, 'F' => 18, 'G' => 16, 'H' => 14] as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        $sheet->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_PORTRAIT)
            ->setPaperSize(PageSetup::PAPERSIZE_A4)
            ->setFitToWidth(1)
            ->setFitToHeight(0);
        $sheet->getPageMargins()->setTop(0.4)->setBottom(0.4)->setLeft(0.3)->setRight(0.3);
    }

    private function writeCards(Worksheet $sheet, int $labelRow, array $cards): void
    {
        $valueRow = $labelRow + 1;

        foreach ($cards as [$startCol, $endCol, $label, $value, $format, $bg, $fontColor]) {
            $labelRange = $startCol . $labelRow . ':' . $endCol . $labelRow;
            $valueRange = $startCol . $valueRow . ':' . $endCol . $valueRow;

            $sheet->mergeCells($labelRange);
            $sheet->mergeCells($valueRange);

            $sheet->setCellValue($startCol . $labelRow, $label);
            $sheet->setCellValue($startCol . $valueRow, $value);

            $sheet->getStyle($labelRange)->applyFromArray([
                'font' => ['bold' => true, 'size' => 8, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::NAVY]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'FFFFFF']]],
            ]);

            $sheet->getStyle($valueRange)->applyFromArray([
                'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => $fontColor]],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bg]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D1D5DB']]],
            ]);
            $sheet->getStyle($startCol . $valueRow)->getNumberFormat()->setFormatCode($format);
        }

        $sheet->getRowDimension($labelRow)->setRowHeight(20);
        $sheet->getRowDimension($valueRow)->setRowHeight(26);
    }

    private function writeTitleBlock(Worksheet $sheet, string $title, int $lastCol, array $report): void
    {
        $lastLetter = $this->columnLetter($lastCol);

        $sheet->mergeCells('A1:' . $lastLetter . '1');
        $sheet->setCellValue('A1', $title);
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => self::NAVY]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(28);

        $sheet->mergeCells('A2:' . $lastLetter . '2');
        $sheet->setCellValue('A2', self::ORG_NAME);
        $sheet->getStyle('A2')->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => '374151']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $sheet->mergeCells('A3:' . $lastLetter . '3');
        $sheet->setCellValue('A3', $report['filterText'] ?? '');
        $sheet->getStyle('A3')->applyFromArray([
            'font' => ['size' => 9, 'italic' => true, 'color' => ['rgb' => '6B7280']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
        $sheet->getRowDimension(3)->setRowHeight(18);

        $sheet->mergeCells('A4:' . $lastLetter . '4');
        $sheet->setCellValue('A4', 'Dicetak ' . ($report['generatedAt'] ?? date('d-m-Y H:i')) . ' dari Dashboard Anggaran');
        $sheet->getStyle('A4')->applyFromArray([
            'font' => ['size' => 8, 'italic' => true, 'color' => ['rgb' => '9CA3AF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
    }

    // ──────────────────────────────────────────────────────────
    // SHEET 2 — Rekap pengajuan
    // ──────────────────────────────────────────────────────────

    private function buildListSheet(Worksheet $sheet, array $report): void
    {
        $sheet->setTitle(self::SHEET_LIST);

        $this->writeTitleBlock($sheet, 'REKAP PENGAJUAN TU', 18, $report);

        $headers = [
            'A' => 'NO',
            'B' => 'NOMOR TU',
            'C' => 'TGL PENGAJUAN',
            'D' => 'SEKSI',
            'E' => 'PERIODE',
            'F' => 'KEPERLUAN',
            'G' => "JML\nREKENING",
            'H' => "KEBUTUHAN\n(Rp)",
            'I' => 'STATUS',
            'J' => 'NO. SP2D',
            'K' => 'TGL CAIR',
            'L' => "NOMINAL CAIR\n(Rp)",
            'M' => "TERPAKAI\n(Rp)",
            'N' => "DISETOR\n(Rp)",
            'O' => "SISA DANA\n(Rp)",
            'P' => 'BATAS SPJ',
            'Q' => 'DIBUAT OLEH',
            'R' => 'KETERANGAN',
        ];
        $this->writeHeaderRow($sheet, $headers, self::LIST_LAST_COL, 36);

        $rows = $report['rows'] ?? [];
        $row = self::FIRST_DATA_ROW;

        if (empty($rows)) {
            $this->writeEmptyRow($sheet, self::LIST_LAST_COL, 'Tidak ada pengajuan TU yang cocok dengan filter ini.');
            $this->configureSheet($sheet, $this->listWidths(), self::LIST_LAST_COL, null);

            return;
        }

        $index = 1;
        foreach ($rows as $data) {
            $rowBg = ($index % 2) === 0 ? self::ZEBRA : 'FFFFFF';

            $sheet->setCellValue('A' . $row, $index);
            $sheet->setCellValue('B' . $row, (string) $data['nomor_pengajuan']);
            $sheet->setCellValue('C' . $row, $this->formatDate($data['tanggal_pengajuan'] ?? null));
            $sheet->setCellValue('D' . $row, (string) $data['seksi']);
            $sheet->setCellValue('E' . $row, (string) $data['periode']);
            $sheet->setCellValue('F' . $row, (string) $data['keperluan']);
            $sheet->setCellValue('G' . $row, (int) $data['jumlah_rekening']);
            $sheet->setCellValue('H' . $row, (float) $data['kebutuhan']);
            $sheet->setCellValue('I' . $row, self::STATUS_LABEL[$data['status']] ?? (string) $data['status']);
            $sheet->setCellValue('J' . $row, (string) ($data['nomor_sp2d'] ?? ''));
            $sheet->setCellValue('K' . $row, $this->formatDate($data['tanggal_cair'] ?? null));
            $sheet->setCellValue('L' . $row, (float) $data['nominal_cair']);
            $sheet->setCellValue('M' . $row, (float) $data['nominal_terpakai']);
            $sheet->setCellValue('N' . $row, (float) $data['nominal_disetor']);
            $sheet->setCellValue('O' . $row, (float) $data['sisa']);
            $sheet->setCellValue('P' . $row, $this->formatDate($data['batas_spj'] ?? null));
            $sheet->setCellValue('Q' . $row, (string) ($data['created_by'] ?? ''));
            $sheet->setCellValue('R' . $row, (string) $data['keterangan']);

            $sheet->getStyle('A' . $row . ':' . self::LIST_LAST_COL . $row)->applyFromArray([
                'font' => ['size' => 9, 'color' => ['rgb' => '1F2937']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $rowBg]],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E5E7EB']]],
            ]);

            $this->formatNumberCells($sheet, ['H', 'L', 'M', 'N', 'O'], $row);
            foreach (['A', 'C', 'E', 'G', 'K', 'P'] as $col) {
                $sheet->getStyle($col . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }
            $sheet->getStyle('G' . $row)->getNumberFormat()->setFormatCode(self::NUMBER_FORMAT);

            [$statusFont, $statusBg] = $this->statusColors((string) $data['status']);
            $sheet->getStyle('I' . $row)->getFont()->setBold(true);
            $sheet->getStyle('I' . $row)->getFont()->getColor()->setRGB($statusFont);
            $sheet->getStyle('I' . $row)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB($statusBg);
            $sheet->getStyle('I' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $sisa = (float) $data['sisa'];
            $cair = (float) $data['nominal_cair'];
            $sheet->getStyle('O' . $row)->getFont()->setBold(true);
            $sheet->getStyle('O' . $row)->getFont()->getColor()->setRGB(
                $cair <= 0.005 ? '6B7280' : ($sisa > 0.005 ? '92400E' : '166534')
            );

            if (!empty($data['jatuh_tempo'])) {
                $sheet->getStyle('P' . $row)->getFont()->setBold(true);
                $sheet->getStyle('P' . $row)->getFont()->getColor()->setRGB('B91C1C');
                $sheet->getStyle('P' . $row)->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('FEE2E2');
            }

            $sheet->getRowDimension($row)->setRowHeight(32);
            $row++;
            $index++;
        }

        $lastDataRow = $row - 1;
        $sheet->mergeCells('A' . $row . ':G' . $row);
        $sheet->setCellValue('A' . $row, 'JUMLAH TOTAL (' . count($rows) . ' pengajuan)');
        foreach (['H', 'L', 'M', 'N', 'O'] as $col) {
            $sheet->setCellValue($col . $row, '=SUM(' . $col . self::FIRST_DATA_ROW . ':' . $col . $lastDataRow . ')');
        }
        $sheet->getStyle('A' . $row . ':' . self::LIST_LAST_COL . $row)->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::NAVY]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => 'FFFFFF']]],
        ]);
        $this->formatNumberCells($sheet, ['H', 'L', 'M', 'N', 'O'], $row);
        $sheet->getRowDimension($row)->setRowHeight(24);

        $footerRow = $row + 2;
        $sheet->mergeCells('A' . $footerRow . ':' . self::LIST_LAST_COL . $footerRow);
        $sheet->setCellValue(
            'A' . $footerRow,
            'Sisa Dana = Nominal Cair − Terpakai − Disetor. Baris berwarna merah pada Batas SPJ berarti tenggat SPJ sudah terlewat.'
        );
        $sheet->getStyle('A' . $footerRow)->applyFromArray([
            'font' => ['size' => 8, 'italic' => true, 'color' => ['rgb' => '6B7280']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);

        $this->configureSheet($sheet, $this->listWidths(), self::LIST_LAST_COL, $lastDataRow);
    }

    // ──────────────────────────────────────────────────────────
    // SHEET 3 — Detail rekening
    // ──────────────────────────────────────────────────────────

    private function buildRekeningSheet(Worksheet $sheet, array $report): void
    {
        $sheet->setTitle(self::SHEET_REKENING);

        $this->writeTitleBlock($sheet, 'DETAIL REKENING PER PENGAJUAN TU', 10, $report);

        $headers = [
            'A' => 'NO',
            'B' => 'NOMOR TU',
            'C' => 'TGL PENGAJUAN',
            'D' => 'SEKSI',
            'E' => 'KODE REKENING',
            'F' => 'NAMA REKENING',
            'G' => 'SUB KEGIATAN',
            'H' => "NOMINAL\n(Rp)",
            'I' => 'STATUS TU',
            'J' => 'PERIODE',
        ];
        $this->writeHeaderRow($sheet, $headers, self::REKENING_LAST_COL, 30);

        $rows = $report['rekenings'] ?? [];
        $row = self::FIRST_DATA_ROW;

        if (empty($rows)) {
            $this->writeEmptyRow($sheet, self::REKENING_LAST_COL, 'Tidak ada rincian rekening pada filter ini.');
            $this->configureSheet($sheet, $this->rekeningWidths(), self::REKENING_LAST_COL, null);

            return;
        }

        $index = 1;
        foreach ($rows as $data) {
            $rowBg = ($index % 2) === 0 ? self::ZEBRA : 'FFFFFF';

            $sheet->setCellValue('A' . $row, $index);
            $sheet->setCellValue('B' . $row, (string) $data['nomor_pengajuan']);
            $sheet->setCellValue('C' . $row, $this->formatDate($data['tanggal_pengajuan'] ?? null));
            $sheet->setCellValue('D' . $row, (string) $data['seksi']);
            $sheet->setCellValue('E' . $row, (string) $data['kode_rekening']);
            $sheet->setCellValue('F' . $row, (string) $data['nama_rekening']);
            $sheet->setCellValue('G' . $row, trim(($data['kode_sub_kegiatan'] ?? '') . ' — ' . ($data['nama_sub_kegiatan'] ?? ''), ' —'));
            $sheet->setCellValue('H' . $row, (float) $data['nominal']);
            $sheet->setCellValue('I' . $row, self::STATUS_LABEL[$data['status']] ?? (string) $data['status']);
            $sheet->setCellValue('J' . $row, (string) $data['periode']);

            $sheet->getStyle('A' . $row . ':' . self::REKENING_LAST_COL . $row)->applyFromArray([
                'font' => ['size' => 9, 'color' => ['rgb' => '1F2937']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $rowBg]],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E5E7EB']]],
            ]);
            $this->formatNumberCells($sheet, ['H'], $row);
            foreach (['A', 'C', 'E', 'J'] as $col) {
                $sheet->getStyle($col . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }

            [$statusFont, $statusBg] = $this->statusColors((string) $data['status']);
            $sheet->getStyle('I' . $row)->getFont()->setBold(true);
            $sheet->getStyle('I' . $row)->getFont()->getColor()->setRGB($statusFont);
            $sheet->getStyle('I' . $row)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB($statusBg);
            $sheet->getStyle('I' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $sheet->getRowDimension($row)->setRowHeight(30);
            $row++;
            $index++;
        }

        $lastDataRow = $row - 1;
        $sheet->mergeCells('A' . $row . ':G' . $row);
        $sheet->setCellValue('A' . $row, 'JUMLAH TOTAL (' . count($rows) . ' rincian rekening)');
        $sheet->setCellValue('H' . $row, '=SUM(H' . self::FIRST_DATA_ROW . ':H' . $lastDataRow . ')');
        $sheet->getStyle('A' . $row . ':' . self::REKENING_LAST_COL . $row)->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::NAVY]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => 'FFFFFF']]],
        ]);
        $this->formatNumberCells($sheet, ['H'], $row);
        $sheet->getRowDimension($row)->setRowHeight(24);

        $this->configureSheet($sheet, $this->rekeningWidths(), self::REKENING_LAST_COL, $lastDataRow);
    }

    // ──────────────────────────────────────────────────────────
    // SHEET 4 — Riwayat pembayaran
    // ──────────────────────────────────────────────────────────

    private function buildPaymentSheet(Worksheet $sheet, array $report): void
    {
        $sheet->setTitle(self::SHEET_PAYMENT);

        $this->writeTitleBlock($sheet, 'RIWAYAT PEMBAYARAN DARI DANA TU', 12, $report);

        $headers = [
            'A' => 'NO',
            'B' => 'NOMOR TU',
            'C' => 'TGL BAYAR',
            'D' => 'KODE REKENING',
            'E' => 'NAMA REKENING',
            'F' => 'SUB KEGIATAN',
            'G' => 'URAIAN',
            'H' => 'PENERIMA',
            'I' => 'JENIS',
            'J' => 'STATUS',
            'K' => 'NO. BUKTI',
            'L' => "NILAI\n(Rp)",
        ];
        $this->writeHeaderRow($sheet, $headers, self::PAYMENT_LAST_COL, 32);

        $rows = $report['transaksis'] ?? [];
        $row = self::FIRST_DATA_ROW;

        if (empty($rows)) {
            $this->writeEmptyRow($sheet, self::PAYMENT_LAST_COL, 'Belum ada pembayaran dari dana TU pada filter ini.');
            $this->configureSheet($sheet, $this->paymentWidths(), self::PAYMENT_LAST_COL, null);

            return;
        }

        $index = 1;
        foreach ($rows as $data) {
            $rowBg = ($index % 2) === 0 ? self::ZEBRA : 'FFFFFF';

            $sheet->setCellValue('A' . $row, $index);
            $sheet->setCellValue('B' . $row, (string) $data['nomor_pengajuan']);
            $sheet->setCellValue('C' . $row, $this->formatDate($data['tanggal'] ?? null));
            $sheet->setCellValue('D' . $row, (string) $data['kode_rekening']);
            $sheet->setCellValue('E' . $row, (string) $data['nama_rekening']);
            $sheet->setCellValue('F' . $row, trim(($data['kode_sub_kegiatan'] ?? '') . ' — ' . ($data['nama_sub_kegiatan'] ?? ''), ' —'));
            $sheet->setCellValue('G' . $row, (string) $data['uraian']);
            $sheet->setCellValue('H' . $row, (string) ($data['nama_penerima'] ?? ''));
            $sheet->setCellValue('I' . $row, self::JENIS_LABEL[$data['jenis_transaksi']] ?? (string) $data['jenis_transaksi']);
            $sheet->setCellValue('J' . $row, self::TRANSAKSI_LABEL[$data['status']] ?? (string) $data['status']);
            $sheet->setCellValue('K' . $row, (string) ($data['nomor_bukti'] ?? ''));
            $sheet->setCellValue('L' . $row, (float) $data['nilai']);

            $sheet->getStyle('A' . $row . ':' . self::PAYMENT_LAST_COL . $row)->applyFromArray([
                'font' => ['size' => 9, 'color' => ['rgb' => '1F2937']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $rowBg]],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E5E7EB']]],
            ]);
            $this->formatNumberCells($sheet, ['L'], $row);
            foreach (['A', 'C', 'D', 'I', 'J', 'K'] as $col) {
                $sheet->getStyle($col . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }

            if ($data['status'] === 'diverifikasi') {
                $sheet->getStyle('J' . $row)->getFont()->setBold(true);
                $sheet->getStyle('J' . $row)->getFont()->getColor()->setRGB('166534');
                $sheet->getStyle('J' . $row)->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('DCFCE7');
            } elseif ($data['status'] === 'ditolak') {
                $sheet->getStyle('J' . $row)->getFont()->setBold(true);
                $sheet->getStyle('J' . $row)->getFont()->getColor()->setRGB('B91C1C');
            }

            $sheet->getRowDimension($row)->setRowHeight(30);
            $row++;
            $index++;
        }

        $lastDataRow = $row - 1;
        $sheet->mergeCells('A' . $row . ':K' . $row);
        $sheet->setCellValue('A' . $row, 'TOTAL TERVERIFIKASI (' . count($rows) . ' pembayaran)');
        $sheet->setCellValue('L' . $row, '=SUMIF(J' . self::FIRST_DATA_ROW . ':J' . $lastDataRow . ',"Terverifikasi",L' . self::FIRST_DATA_ROW . ':L' . $lastDataRow . ')');
        $sheet->getStyle('A' . $row . ':' . self::PAYMENT_LAST_COL . $row)->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::NAVY]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => 'FFFFFF']]],
        ]);
        $this->formatNumberCells($sheet, ['L'], $row);
        $sheet->getRowDimension($row)->setRowHeight(24);

        $footerRow = $row + 2;
        $sheet->mergeCells('A' . $footerRow . ':' . self::PAYMENT_LAST_COL . $footerRow);
        $sheet->setCellValue(
            'A' . $footerRow,
            'Hanya pembayaran berstatus Terverifikasi yang dihitung sebagai dana TU terpakai pada ringkasan.'
        );
        $sheet->getStyle('A' . $footerRow)->applyFromArray([
            'font' => ['size' => 8, 'italic' => true, 'color' => ['rgb' => '6B7280']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);

        $this->configureSheet($sheet, $this->paymentWidths(), self::PAYMENT_LAST_COL, $lastDataRow);
    }

    // ──────────────────────────────────────────────────────────
    // Helper
    // ──────────────────────────────────────────────────────────

    private function writeHeaderRow(Worksheet $sheet, array $headers, string $lastCol, int $rowHeight): void
    {
        foreach ($headers as $col => $label) {
            $sheet->setCellValue($col . self::HEADER_ROW, $label);
        }
        $sheet->getStyle('A' . self::HEADER_ROW . ':' . $lastCol . self::HEADER_ROW)->applyFromArray([
            'font' => ['bold' => true, 'size' => 9, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::NAVY]],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'FFFFFF']]],
        ]);
        $sheet->getRowDimension(self::HEADER_ROW)->setRowHeight($rowHeight);
    }

    private function writeEmptyRow(Worksheet $sheet, string $lastCol, string $message): void
    {
        $row = self::FIRST_DATA_ROW;
        $sheet->mergeCells('A' . $row . ':' . $lastCol . $row);
        $sheet->setCellValue('A' . $row, $message);
        $sheet->getStyle('A' . $row . ':' . $lastCol . $row)->applyFromArray([
            'font' => ['italic' => true, 'size' => 10, 'color' => ['rgb' => '6B7280']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D1D5DB']]],
        ]);
        $sheet->getRowDimension($row)->setRowHeight(24);
    }

    private function configureSheet(Worksheet $sheet, array $widths, string $lastCol, ?int $lastDataRow): void
    {
        foreach ($widths as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        if ($lastDataRow !== null && $lastDataRow >= self::FIRST_DATA_ROW) {
            $sheet->setAutoFilter('A' . self::HEADER_ROW . ':' . $lastCol . $lastDataRow);
        }

        $sheet->freezePane('A' . self::FIRST_DATA_ROW);
        $sheet->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
            ->setPaperSize(PageSetup::PAPERSIZE_A4)
            ->setFitToWidth(1)
            ->setFitToHeight(0)
            ->setRowsToRepeatAtTopByStartAndEnd(self::HEADER_ROW, self::HEADER_ROW);
        $sheet->getPageMargins()->setTop(0.4)->setBottom(0.4)->setLeft(0.3)->setRight(0.3);
    }

    private function listWidths(): array
    {
        return [
            'A' => 5, 'B' => 24, 'C' => 13, 'D' => 24, 'E' => 14,
            'F' => 38, 'G' => 9, 'H' => 15, 'I' => 16, 'J' => 20,
            'K' => 12, 'L' => 15, 'M' => 14, 'N' => 13, 'O' => 14,
            'P' => 12, 'Q' => 14, 'R' => 34,
        ];
    }

    private function rekeningWidths(): array
    {
        return [
            'A' => 5, 'B' => 24, 'C' => 13, 'D' => 24, 'E' => 16,
            'F' => 38, 'G' => 38, 'H' => 15, 'I' => 16, 'J' => 14,
        ];
    }

    private function paymentWidths(): array
    {
        return [
            'A' => 5, 'B' => 24, 'C' => 12, 'D' => 16, 'E' => 32, 'F' => 32,
            'G' => 36, 'H' => 20, 'I' => 16, 'J' => 13, 'K' => 18, 'L' => 15,
        ];
    }

    private function formatNumberCells(Worksheet $sheet, array $columns, int $row): void
    {
        foreach ($columns as $col) {
            $sheet->getStyle($col . $row)->getNumberFormat()->setFormatCode(self::NUMBER_FORMAT);
            $sheet->getStyle($col . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }
    }

    private function formatDate(?string $date): string
    {
        if (!$date) {
            return '';
        }
        $time = strtotime($date);
        return $time ? date('d/m/Y', $time) : '';
    }

    /**
     * @return array{0:string,1:string} [warna teks, warna latar]
     */
    private function statusColors(string $status): array
    {
        return match ($status) {
            'diajukan'  => ['92400E', 'FEF3C7'],
            'disetujui' => ['0E7490', 'E0F2FE'],
            'cair'      => ['1D4ED8', 'DBEAFE'],
            'selesai'   => ['166534', 'DCFCE7'],
            'ditolak'   => ['B91C1C', 'FEE2E2'],
            default     => ['334155', 'F1F5F9'],
        };
    }

    private function columnLetter(int $number): string
    {
        $letter = '';
        while ($number > 0) {
            $number--;
            $letter = chr(65 + ($number % 26)) . $letter;
            $number = intdiv($number, 26);
        }

        return $letter;
    }
}
