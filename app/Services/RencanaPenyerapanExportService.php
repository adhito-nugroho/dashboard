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
 * Export rekapitulasi Rencana Penyerapan.
 *
 * Sheet 1: ringkasan KPI + rekap per status.
 * Sheet 2: satu baris per kode rekening (pagu, rencana, realisasi, sisa, status).
 * Sheet 3: proyeksi otomatis bulan berjalan + bulan berikutnya per rekening.
 * Sheet 4: detail bentuk kegiatan manual + rencana otomatis dari TU.
 */
class RencanaPenyerapanExportService
{
    private const SHEET_SUMMARY = 'Ringkasan';
    private const SHEET_MAIN = 'Rekap Rekening';
    private const SHEET_PROJECTION = 'Proyeksi Bulanan';
    private const SHEET_DETAIL = 'Detail Rencana';

    private const HEADER_ROW = 6;
    private const FIRST_DATA_ROW = 7;
    private const LAST_COL = 'Q';
    private const PROJ_LAST_COL = 'W';
    private const DETAIL_LAST_COL = 'J';

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

    public function download(array $report): void
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setCreator('Dashboard Anggaran CDK Wilayah Bojonegoro')
            ->setTitle('Rekap Rencana Penyerapan ' . (int) ($report['tahun'] ?? date('Y')))
            ->setSubject('Rekapitulasi Rencana Penyerapan');

        $this->buildSummarySheet($spreadsheet->getActiveSheet(), $report);
        $this->buildMainSheet($spreadsheet->createSheet(), $report);
        $this->buildProjectionSheet($spreadsheet->createSheet(), $report);
        $this->buildDetailSheet($spreadsheet->createSheet(), $report);

        $spreadsheet->setActiveSheetIndex(0);

        $filename = 'Rekap_Rencana_Penyerapan_' . (int) ($report['tahun'] ?? date('Y')) . '.xlsx';

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
        $tahun = (int) ($report['tahun'] ?? date('Y'));
        $totals = $report['totals'] ?? [];

        $this->writeTitleBlock($sheet, 'REKAPITULASI RENCANA PENYERAPAN ANGGARAN ' . $tahun, 8, $report);

        $pctRencana = ((float) ($totals['pagu'] ?? 0)) > 0
            ? ((float) $totals['total_rencana']) / (float) $totals['pagu']
            : 0.0;
        $pctRealisasi = ((float) ($totals['pagu'] ?? 0)) > 0
            ? ((float) $totals['realisasi']) / (float) $totals['pagu']
            : 0.0;
        $belumCount = (int) ($report['statusGroups']['Belum ada rencana']['count'] ?? 0);

        $cardsTop = [
            ['A', 'B', 'TOTAL PAGU', (float) ($totals['pagu'] ?? 0), self::NUMBER_FORMAT, 'EEF2FF', self::NAVY],
            ['C', 'D', 'TOTAL RENCANA', (float) ($totals['total_rencana'] ?? 0), self::NUMBER_FORMAT, 'E0E7FF', '3730A3'],
            ['E', 'F', 'REALISASI TERVERIFIKASI', (float) ($totals['realisasi'] ?? 0), self::NUMBER_FORMAT, 'DCFCE7', '166534'],
            ['G', 'H', 'SISA KAPASITAS', (float) ($totals['sisa'] ?? 0), self::SIGNED_FORMAT, 'FEF3C7', '92400E'],
        ];
        $cardsBottom = [
            ['A', 'B', 'RENCANA TERHADAP PAGU', $pctRencana, self::PERCENT_FORMAT, 'F1F5F9', '334155'],
            ['C', 'D', 'REALISASI TERHADAP PAGU', $pctRealisasi, self::PERCENT_FORMAT, 'F1F5F9', '334155'],
            ['E', 'F', 'JUMLAH REKENING', (int) ($totals['count'] ?? 0), self::NUMBER_FORMAT, 'F1F5F9', '334155'],
            ['G', 'H', 'REKENING BELUM DIRENCANAKAN', $belumCount, self::NUMBER_FORMAT, 'FEF3C7', '92400E'],
        ];

        $this->writeCards($sheet, 7, $cardsTop);
        $this->writeCards($sheet, 9, $cardsBottom);

        $sheet->mergeCells('A12:H12');
        $sheet->setCellValue('A12', 'REKAP PER STATUS REKENING');
        $sheet->getStyle('A12:H12')->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::NAVY]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(12)->setRowHeight(22);

        $headers = [
            'A' => 'STATUS',
            'B' => 'JUMLAH REKENING',
            'C' => 'PAGU (Rp)',
            'D' => 'RENCANA MANUAL (Rp)',
            'E' => 'RENCANA TU (Rp)',
            'F' => 'TOTAL RENCANA (Rp)',
            'G' => 'REALISASI (Rp)',
            'H' => 'SISA KAPASITAS (Rp)',
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

        $row = 14;
        foreach ($report['statusGroups'] ?? [] as $label => $group) {
            $sheet->setCellValue('A' . $row, $label);
            $sheet->setCellValue('B' . $row, (int) $group['count']);
            $sheet->setCellValue('C' . $row, (float) $group['pagu']);
            $sheet->setCellValue('D' . $row, (float) $group['rencana_manual']);
            $sheet->setCellValue('E' . $row, (float) $group['rencana_tu']);
            $sheet->setCellValue('F' . $row, (float) $group['total_rencana']);
            $sheet->setCellValue('G' . $row, (float) $group['realisasi']);
            $sheet->setCellValue('H' . $row, (float) $group['sisa']);

            [$fontColor, $bgColor] = $this->statusColors((string) $label);
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
            $this->formatNumberCells($sheet, ['C', 'D', 'E', 'F', 'G', 'H'], $row);
            $sheet->getStyle('B' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('H' . $row)->getFont()->setBold(true);
            $sheet->getStyle('H' . $row)->getFont()->getColor()
                ->setRGB((float) $group['sisa'] < 0 ? 'B91C1C' : '166534');
            $sheet->getRowDimension($row)->setRowHeight(20);
            $row++;
        }

        if (empty($report['statusGroups'])) {
            $sheet->mergeCells('A' . $row . ':H' . $row);
            $sheet->setCellValue('A' . $row, 'Tidak ada rekening yang cocok dengan filter ini.');
            $sheet->getStyle('A' . $row . ':H' . $row)->applyFromArray([
                'font' => ['italic' => true, 'size' => 9, 'color' => ['rgb' => '6B7280']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D1D5DB']]],
            ]);
            $row++;
        }

        $sheet->setCellValue('A' . $row, 'JUMLAH TOTAL');
        $sheet->setCellValue('B' . $row, (int) ($totals['count'] ?? 0));
        $sheet->setCellValue('C' . $row, (float) ($totals['pagu'] ?? 0));
        $sheet->setCellValue('D' . $row, (float) ($totals['rencana_manual'] ?? 0));
        $sheet->setCellValue('E' . $row, (float) ($totals['rencana_tu'] ?? 0));
        $sheet->setCellValue('F' . $row, (float) ($totals['total_rencana'] ?? 0));
        $sheet->setCellValue('G' . $row, (float) ($totals['realisasi'] ?? 0));
        $sheet->setCellValue('H' . $row, (float) ($totals['sisa'] ?? 0));
        $sheet->getStyle('A' . $row . ':H' . $row)->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::NAVY]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => 'FFFFFF']]],
        ]);
        $this->formatNumberCells($sheet, ['C', 'D', 'E', 'F', 'G', 'H'], $row);
        $sheet->getStyle('B' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getRowDimension($row)->setRowHeight(22);

        $notes = [
            'Sisa kapasitas = Pagu − Realisasi terverifikasi − Rencana manual − Rencana TU.',
            'Rencana TU adalah sisa pengajuan TU berjalan (diajukan/disetujui/cair) yang belum terverifikasi; tidak perlu entri ulang.',
            'Status "Selesai (sisa 0)" berarti pagu sudah habis oleh realisasi dan rencana, atau rekening ditandai selesai manual.',
            'Data mengikuti filter halaman Rencana Penyerapan saat tombol unduh diklik.',
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

        foreach (['A' => 30, 'B' => 18, 'C' => 18, 'D' => 20, 'E' => 18, 'F' => 20, 'G' => 18, 'H' => 20] as $col => $width) {
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
    // SHEET 2 — Rekap per rekening
    // ──────────────────────────────────────────────────────────

    private function buildMainSheet(Worksheet $sheet, array $report): void
    {
        $sheet->setTitle(self::SHEET_MAIN);

        $this->writeTitleBlock($sheet, 'REKAP RENCANA PENYERAPAN PER KODE REKENING', 17, $report);

        $headers = [
            'A' => 'NO',
            'B' => 'SEKSI',
            'C' => 'PROGRAM',
            'D' => 'KEGIATAN',
            'E' => 'SUB KEGIATAN',
            'F' => 'KODE REKENING',
            'G' => 'NAMA REKENING',
            'H' => "PAGU\n(Rp)",
            'I' => "RENCANA\nMANUAL (Rp)",
            'J' => "RENCANA\nTU (Rp)",
            'K' => "TOTAL\nRENCANA (Rp)",
            'L' => "RENCANA\nTERHADAP PAGU",
            'M' => "REALISASI\n(Rp)",
            'N' => "REALISASI\nTERHADAP PAGU",
            'O' => "SISA\nKAPASITAS (Rp)",
            'P' => 'STATUS',
            'Q' => 'KETERANGAN',
        ];
        foreach ($headers as $col => $label) {
            $sheet->setCellValue($col . self::HEADER_ROW, $label);
        }
        $sheet->getStyle('A' . self::HEADER_ROW . ':' . self::LAST_COL . self::HEADER_ROW)->applyFromArray([
            'font' => ['bold' => true, 'size' => 9, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::NAVY]],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'FFFFFF']]],
        ]);
        $sheet->getRowDimension(self::HEADER_ROW)->setRowHeight(34);

        $rows = $report['rows'] ?? [];
        $row = self::FIRST_DATA_ROW;

        if (empty($rows)) {
            $sheet->mergeCells('A' . $row . ':' . self::LAST_COL . $row);
            $sheet->setCellValue('A' . $row, 'Tidak ada rekening yang cocok dengan filter ini.');
            $sheet->getStyle('A' . $row . ':' . self::LAST_COL . $row)->applyFromArray([
                'font' => ['italic' => true, 'size' => 10, 'color' => ['rgb' => '6B7280']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D1D5DB']]],
            ]);
            $sheet->getRowDimension($row)->setRowHeight(24);
            $this->configureMainSheet($sheet, 0);

            return;
        }

        $index = 1;
        foreach ($rows as $data) {
            $pagu = (float) $data['pagu'];
            $totalRencana = (float) $data['total_rencana'];
            $realisasi = (float) $data['realisasi'];
            $sisa = (float) $data['sisa'];
            $isZebra = ($index % 2) === 0;
            $rowBg = $isZebra ? self::ZEBRA : 'FFFFFF';

            $sheet->setCellValue('A' . $row, $index);
            $sheet->setCellValue('B' . $row, (string) $data['nama_seksi']);
            $sheet->setCellValue('C' . $row, trim($data['kode_program'] . ' — ' . $data['nama_program'], ' —'));
            $sheet->setCellValue('D' . $row, trim($data['kode_kegiatan'] . ' — ' . $data['nama_kegiatan'], ' —'));
            $sheet->setCellValue('E' . $row, trim($data['kode_sub_kegiatan'] . ' — ' . $data['nama_sub_kegiatan'], ' —'));
            $sheet->setCellValue('F' . $row, (string) $data['kode_rekening']);
            $sheet->setCellValue('G' . $row, (string) $data['nama_rekening']);
            $sheet->setCellValue('H' . $row, $pagu);
            $sheet->setCellValue('I' . $row, (float) $data['rencana_manual']);
            $sheet->setCellValue('J' . $row, (float) $data['rencana_tu']);
            $sheet->setCellValue('K' . $row, $totalRencana);
            $sheet->setCellValue('L' . $row, $pagu > 0 ? $totalRencana / $pagu : 0);
            $sheet->setCellValue('M' . $row, $realisasi);
            $sheet->setCellValue('N' . $row, $pagu > 0 ? $realisasi / $pagu : 0);
            $sheet->setCellValue('O' . $row, $sisa);
            $sheet->setCellValue('P' . $row, (string) $data['status']);
            $sheet->setCellValue('Q' . $row, (string) $data['keterangan']);

            $sheet->getStyle('A' . $row . ':' . self::LAST_COL . $row)->applyFromArray([
                'font' => ['size' => 9, 'color' => ['rgb' => '1F2937']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $rowBg]],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E5E7EB']]],
            ]);

            $this->formatNumberCells($sheet, ['H', 'I', 'J', 'K', 'M', 'O'], $row);
            foreach (['L', 'N'] as $col) {
                $sheet->getStyle($col . $row)->getNumberFormat()->setFormatCode(self::PERCENT_FORMAT);
                $sheet->getStyle($col . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }
            $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('F' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $sheet->getStyle('O' . $row)->getFont()->setBold(true);
            $sheet->getStyle('O' . $row)->getFont()->getColor()->setRGB($sisa < 0 ? 'B91C1C' : '166534');
            $sheet->getStyle('O' . $row)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB($sisa < 0 ? 'FEE2E2' : 'F0FDF4');

            [$statusFont, $statusBg] = $this->statusColors((string) $data['status']);
            $sheet->getStyle('P' . $row)->getFont()->setBold(true);
            $sheet->getStyle('P' . $row)->getFont()->getColor()->setRGB($statusFont);
            $sheet->getStyle('P' . $row)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB($statusBg);
            $sheet->getStyle('P' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $sheet->getRowDimension($row)->setRowHeight(34);
            $row++;
            $index++;
        }

        $lastDataRow = $row - 1;
        $sheet->mergeCells('A' . $row . ':G' . $row);
        $sheet->setCellValue('A' . $row, 'JUMLAH TOTAL (' . count($rows) . ' rekening)');
        $sheet->setCellValue('H' . $row, '=SUM(H' . self::FIRST_DATA_ROW . ':H' . $lastDataRow . ')');
        $sheet->setCellValue('I' . $row, '=SUM(I' . self::FIRST_DATA_ROW . ':I' . $lastDataRow . ')');
        $sheet->setCellValue('J' . $row, '=SUM(J' . self::FIRST_DATA_ROW . ':J' . $lastDataRow . ')');
        $sheet->setCellValue('K' . $row, '=SUM(K' . self::FIRST_DATA_ROW . ':K' . $lastDataRow . ')');
        $sheet->setCellValue('L' . $row, '=IF(H' . $row . '=0,0,K' . $row . '/H' . $row . ')');
        $sheet->setCellValue('M' . $row, '=SUM(M' . self::FIRST_DATA_ROW . ':M' . $lastDataRow . ')');
        $sheet->setCellValue('N' . $row, '=IF(H' . $row . '=0,0,M' . $row . '/H' . $row . ')');
        $sheet->setCellValue('O' . $row, '=SUM(O' . self::FIRST_DATA_ROW . ':O' . $lastDataRow . ')');
        $sheet->setCellValue('P' . $row, '');
        $sheet->setCellValue('Q' . $row, '');

        $sheet->getStyle('A' . $row . ':' . self::LAST_COL . $row)->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::NAVY]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => 'FFFFFF']]],
        ]);
        $this->formatNumberCells($sheet, ['H', 'I', 'J', 'K', 'M', 'O'], $row);
        foreach (['L', 'N'] as $col) {
            $sheet->getStyle($col . $row)->getNumberFormat()->setFormatCode(self::PERCENT_FORMAT);
            $sheet->getStyle($col . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }
        $sheet->getRowDimension($row)->setRowHeight(24);

        $footerRow = $row + 2;
        $sheet->mergeCells('A' . $footerRow . ':' . self::LAST_COL . $footerRow);
        $sheet->setCellValue(
            'A' . $footerRow,
            'Sisa kapasitas = Pagu − Realisasi terverifikasi − Rencana manual − Rencana TU. Rekening tanpa rencana tetap ditampilkan sebagai pengingat.'
        );
        $sheet->getStyle('A' . $footerRow)->applyFromArray([
            'font' => ['size' => 8, 'italic' => true, 'color' => ['rgb' => '6B7280']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);

        $this->configureMainSheet($sheet, $lastDataRow);
    }

    private function configureMainSheet(Worksheet $sheet, int $lastDataRow): void
    {
        $widths = [
            'A' => 5,
            'B' => 20,
            'C' => 34,
            'D' => 34,
            'E' => 38,
            'F' => 18,
            'G' => 40,
            'H' => 15,
            'I' => 15,
            'J' => 14,
            'K' => 15,
            'L' => 11,
            'M' => 15,
            'N' => 11,
            'O' => 16,
            'P' => 20,
            'Q' => 40,
        ];
        foreach ($widths as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        if ($lastDataRow >= self::FIRST_DATA_ROW) {
            $sheet->setAutoFilter('A' . self::HEADER_ROW . ':' . self::LAST_COL . $lastDataRow);
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

    // ──────────────────────────────────────────────────────────
    // SHEET 3 — Proyeksi bulanan
    // ──────────────────────────────────────────────────────────

    private function buildProjectionSheet(Worksheet $sheet, array $report): void
    {
        $sheet->setTitle(self::SHEET_PROJECTION);

        $this->writeTitleBlock($sheet, 'PROYEKSI RENCANA PENYERAPAN OTOMATIS PER REKENING', 23, $report);

        $projection = $report['projection'] ?? null;
        if (empty($projection['rows'])) {
            $sheet->mergeCells('A' . self::HEADER_ROW . ':' . self::PROJ_LAST_COL . self::HEADER_ROW);
            $sheet->setCellValue('A' . self::HEADER_ROW, 'Tidak ada rekening ber-pagu pada filter ini.');
            $sheet->getStyle('A' . self::HEADER_ROW . ':' . self::PROJ_LAST_COL . self::HEADER_ROW)->applyFromArray([
                'font' => ['italic' => true, 'size' => 10, 'color' => ['rgb' => '6B7280']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            ]);
            $this->configureProjectionSheet($sheet, 0);
            return;
        }

        $bulan = (int) $projection['bulan'];
        $bulanDepan = $projection['bulan_depan'] !== null ? (int) $projection['bulan_depan'] : null;
        $labelBulan = self::BULAN[$bulan] ?? '';
        $labelDepan = $bulanDepan !== null ? (self::BULAN[$bulanDepan] ?? '') : '—';
        $look = $projection['lookback_labels'] ?? [];
        $lookRange = count($look) === 3 ? $look[0] . '–' . $look[2] : implode(', ', $look);

        $headers = [
            'A' => 'NO',
            'B' => 'SEKSI',
            'C' => 'PROGRAM',
            'D' => 'KEGIATAN',
            'E' => 'SUB KEGIATAN',
            'F' => 'KODE REKENING',
            'G' => 'NAMA REKENING',
            'H' => "PAGU\n(Rp)",
            'I' => "REALISASI\nS/D " . strtoupper($labelBulan) . "\n(Rp)",
            'J' => "RATA-RATA\n" . strtoupper($lookRange) . "\n(Rp)",
            'K' => $labelBulan . "\nGU (Rp)",
            'L' => $labelBulan . "\nTU (Rp)",
            'M' => $labelBulan . "\nLS (Rp)",
            'N' => $labelBulan . "\nJUMLAH (Rp)",
            'O' => $labelDepan . "\nGU (Rp)",
            'P' => $labelDepan . "\nTU (Rp)",
            'Q' => $labelDepan . "\nLS (Rp)",
            'R' => $labelDepan . "\nJUMLAH (Rp)",
            'S' => "PROYEKSI\nSERAPAN (Rp)",
            'T' => "%\nPAGU",
            'U' => "SISA SETELAH\nPROYEKSI (Rp)",
            'V' => "POTENSI\nKEKURANGAN (Rp)",
            'W' => 'KETERANGAN',
        ];
        foreach ($headers as $col => $label) {
            $sheet->setCellValue($col . self::HEADER_ROW, $label);
        }
        $sheet->getStyle('A' . self::HEADER_ROW . ':' . self::PROJ_LAST_COL . self::HEADER_ROW)->applyFromArray([
            'font' => ['bold' => true, 'size' => 8, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::NAVY]],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'FFFFFF']]],
        ]);
        $sheet->getRowDimension(self::HEADER_ROW)->setRowHeight(40);

        $row = self::FIRST_DATA_ROW;
        $index = 1;
        foreach ($projection['rows'] as $data) {
            $isZebra = ($index % 2) === 0;
            $rowBg = $isZebra ? self::ZEBRA : 'FFFFFF';

            $sheet->setCellValue('A' . $row, $index);
            $sheet->setCellValue('B' . $row, (string) ($data['nama_seksi'] ?? ''));
            $sheet->setCellValue('C' . $row, trim(($data['kode_program'] ?? '') . ' — ' . ($data['nama_program'] ?? ''), ' —'));
            $sheet->setCellValue('D' . $row, trim(($data['kode_kegiatan'] ?? '') . ' — ' . ($data['nama_kegiatan'] ?? ''), ' —'));
            $sheet->setCellValue('E' . $row, trim(($data['kode_sub_kegiatan'] ?? '') . ' — ' . ($data['nama_sub_kegiatan'] ?? ''), ' —'));
            $sheet->setCellValue('F' . $row, (string) $data['kode_rekening']);
            $sheet->setCellValue('G' . $row, (string) $data['nama_rekening']);
            $sheet->setCellValue('H' . $row, (float) $data['pagu']);
            $sheet->setCellValue('I' . $row, (float) $data['realisasi_sd']);
            $sheet->setCellValue('J' . $row, (float) $data['rata_rata']);
            $sheet->setCellValue('K' . $row, (float) $data['plan_bulan']['GU']);
            $sheet->setCellValue('L' . $row, (float) $data['plan_bulan']['TU']);
            $sheet->setCellValue('M' . $row, (float) $data['plan_bulan']['LS']);
            $sheet->setCellValue('N' . $row, (float) $data['plan_bulan']['total']);
            $sheet->setCellValue('O' . $row, (float) $data['plan_depan']['GU']);
            $sheet->setCellValue('P' . $row, (float) $data['plan_depan']['TU']);
            $sheet->setCellValue('Q' . $row, (float) $data['plan_depan']['LS']);
            $sheet->setCellValue('R' . $row, (float) $data['plan_depan']['total']);
            $sheet->setCellValue('S' . $row, (float) $data['proyeksi']);
            $sheet->setCellValue('T' . $row, (float) $data['pct_proyeksi']);
            $sheet->setCellValue('U' . $row, (float) $data['sisa_proyeksi']);
            $sheet->setCellValue('V' . $row, (float) $data['kekurangan']);
            $sheet->setCellValue('W' . $row, (string) $data['keterangan']);

            $sheet->getStyle('A' . $row . ':' . self::PROJ_LAST_COL . $row)->applyFromArray([
                'font' => ['size' => 9, 'color' => ['rgb' => '1F2937']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $rowBg]],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E5E7EB']]],
            ]);
            $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $this->formatNumberCells($sheet, ['H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'U', 'V'], $row);
            $sheet->getStyle('T' . $row)->getNumberFormat()->setFormatCode(self::PERCENT_FORMAT);
            $sheet->getStyle('T' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('N' . $row)->getFont()->setBold(true);
            $sheet->getStyle('R' . $row)->getFont()->setBold(true);

            if ((float) $data['sisa_proyeksi'] < -0.5) {
                $sheet->getStyle('U' . $row)->getFont()->setBold(true)->getColor()->setRGB('B91C1C');
            }
            if ((float) $data['kekurangan'] > 0.5) {
                $sheet->getStyle('V' . $row)->getFont()->setBold(true)->getColor()->setRGB('B91C1C');
                $sheet->getStyle('W' . $row)->getFont()->getColor()->setRGB('B91C1C');
            } elseif ((float) $data['rata_rata'] <= 0) {
                $sheet->getStyle('J' . $row)->getFont()->getColor()->setRGB('9CA3AF');
            }

            $sheet->getRowDimension($row)->setRowHeight(26);
            $row++;
            $index++;
        }

        $lastDataRow = $row - 1;
        $totals = $projection['totals'];
        $sheet->mergeCells('A' . $row . ':G' . $row);
        $sheet->setCellValue('A' . $row, 'JUMLAH TOTAL (' . (int) $totals['count'] . ' rekening)');
        $sheet->setCellValue('H' . $row, (float) $totals['pagu']);
        $sheet->setCellValue('I' . $row, (float) $totals['realisasi_sd']);
        $sheet->setCellValue('J' . $row, (float) $totals['rata_rata']);
        $sheet->setCellValue('K' . $row, (float) $totals['plan_bulan']['GU']);
        $sheet->setCellValue('L' . $row, (float) $totals['plan_bulan']['TU']);
        $sheet->setCellValue('M' . $row, (float) $totals['plan_bulan']['LS']);
        $sheet->setCellValue('N' . $row, (float) $totals['plan_bulan']['total']);
        $sheet->setCellValue('O' . $row, (float) $totals['plan_depan']['GU']);
        $sheet->setCellValue('P' . $row, (float) $totals['plan_depan']['TU']);
        $sheet->setCellValue('Q' . $row, (float) $totals['plan_depan']['LS']);
        $sheet->setCellValue('R' . $row, (float) $totals['plan_depan']['total']);
        $sheet->setCellValue('S' . $row, (float) $totals['proyeksi']);
        $sheet->setCellValue('T' . $row, (float) $totals['pagu'] > 0 ? (float) $totals['proyeksi'] / (float) $totals['pagu'] : 0);
        $sheet->setCellValue('U' . $row, (float) $totals['sisa_proyeksi']);
        $sheet->setCellValue('V' . $row, (float) $totals['kekurangan']);
        $sheet->setCellValue('W' . $row, (float) $totals['kekurangan'] > 0.5 ? 'Kekurangan pagu' : 'Cukup');
        $sheet->getStyle('A' . $row . ':' . self::PROJ_LAST_COL . $row)->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::NAVY]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => 'FFFFFF']]],
        ]);
        $this->formatNumberCells($sheet, ['H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'U', 'V'], $row);
        $sheet->getStyle('T' . $row)->getNumberFormat()->setFormatCode(self::PERCENT_FORMAT);
        $sheet->getStyle('T' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getRowDimension($row)->setRowHeight(24);
        $row++;

        $notes = [
            'Rutin = rata-rata realisasi ' . $lookRange . ' dibulatkan; dibatasi sisa pagu (pagu − realisasi − rencana manual − TU).',
            'Rencana ' . $labelBulan . ' = sisa kebutuhan setelah realisasi ' . $labelBulan . ' yang sudah tercatat. '
                . ($bulanDepan !== null ? 'Rencana ' . $labelDepan . ' = satu bulan penuh.' : ''),
            'Rencana manual bentuk kegiatan dan TU sudah dikurangkan dari sisa pagu; kolom TU berasal dari pengajuan TU berjalan.',
            'Proyeksi = realisasi s/d ' . $labelBulan . ' + rencana manual + TU + rencana rutin dua bulan.',
            'GU/LS rutin mengikuti proporsi realisasi ' . $lookRange . '; tanpa pola historis dibebankan ke GU.',
        ];
        foreach ($notes as $note) {
            $sheet->mergeCells('A' . $row . ':' . self::PROJ_LAST_COL . $row);
            $sheet->setCellValue('A' . $row, '• ' . $note);
            $sheet->getStyle('A' . $row)->applyFromArray([
                'font' => ['size' => 8, 'italic' => true, 'color' => ['rgb' => '6B7280']],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            ]);
            $row++;
        }

        $this->configureProjectionSheet($sheet, $lastDataRow);
    }

    private function configureProjectionSheet(Worksheet $sheet, int $lastDataRow): void
    {
        $widths = [
            'A' => 5, 'B' => 16, 'C' => 30, 'D' => 30, 'E' => 32, 'F' => 16, 'G' => 38,
            'H' => 15, 'I' => 15, 'J' => 16, 'K' => 13, 'L' => 13, 'M' => 13, 'N' => 15,
            'O' => 13, 'P' => 13, 'Q' => 13, 'R' => 15, 'S' => 16, 'T' => 9, 'U' => 16,
            'V' => 15, 'W' => 48,
        ];
        foreach ($widths as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        if ($lastDataRow >= self::FIRST_DATA_ROW) {
            $sheet->setAutoFilter('A' . self::HEADER_ROW . ':' . self::PROJ_LAST_COL . $lastDataRow);
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

    // ──────────────────────────────────────────────────────────
    // SHEET 4 — Detail rencana
    // ──────────────────────────────────────────────────────────

    private function buildDetailSheet(Worksheet $sheet, array $report): void
    {
        $sheet->setTitle(self::SHEET_DETAIL);

        $this->writeTitleBlock($sheet, 'DETAIL BENTUK KEGIATAN & MEKANISME', 10, $report);

        $headerRow = self::HEADER_ROW;
        $headers = [
            'A' => 'NO',
            'B' => 'KODE REKENING',
            'C' => 'NAMA REKENING',
            'D' => 'BENTUK KEGIATAN',
            'E' => 'MEKANISME',
            'F' => 'BULAN MULAI',
            'G' => 'BULAN SELESAI',
            'H' => "NILAI RENCANA\n(Rp)",
            'I' => 'KETERANGAN',
            'J' => 'SUMBER',
        ];
        foreach ($headers as $col => $label) {
            $sheet->setCellValue($col . $headerRow, $label);
        }
        $sheet->getStyle('A' . $headerRow . ':' . self::DETAIL_LAST_COL . $headerRow)->applyFromArray([
            'font' => ['bold' => true, 'size' => 9, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::NAVY]],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'FFFFFF']]],
        ]);
        $sheet->getRowDimension($headerRow)->setRowHeight(32);

        $details = $report['detail'] ?? [];
        $row = self::FIRST_DATA_ROW;

        if (empty($details)) {
            $sheet->mergeCells('A' . $row . ':' . self::DETAIL_LAST_COL . $row);
            $sheet->setCellValue('A' . $row, 'Tidak ada rencana pada filter ini.');
            $sheet->getStyle('A' . $row . ':' . self::DETAIL_LAST_COL . $row)->applyFromArray([
                'font' => ['italic' => true, 'size' => 10, 'color' => ['rgb' => '6B7280']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D1D5DB']]],
            ]);
            $sheet->getRowDimension($row)->setRowHeight(24);
        } else {
            $index = 1;
            foreach ($details as $detail) {
                $isZebra = ($index % 2) === 0;
                $rowBg = $isZebra ? self::ZEBRA : 'FFFFFF';
                $mulai = $detail['bulan_mulai'] !== null ? (self::BULAN[(int) $detail['bulan_mulai']] ?? '') : '';
                $selesai = $detail['bulan_selesai'] !== null ? (self::BULAN[(int) $detail['bulan_selesai']] ?? '') : '';

                $sheet->setCellValue('A' . $row, $index);
                $sheet->setCellValue('B' . $row, (string) $detail['kode_rekening']);
                $sheet->setCellValue('C' . $row, (string) $detail['nama_rekening']);
                $sheet->setCellValue('D' . $row, (string) $detail['bentuk_kegiatan']);
                $sheet->setCellValue('E' . $row, (string) $detail['mekanisme']);
                $sheet->setCellValue('F' . $row, $mulai);
                $sheet->setCellValue('G' . $row, $selesai);
                $sheet->setCellValue('H' . $row, (float) $detail['nilai']);
                $sheet->setCellValue('I' . $row, (string) $detail['keterangan']);
                $sheet->setCellValue('J' . $row, (string) $detail['sumber']);

                $sheet->getStyle('A' . $row . ':' . self::DETAIL_LAST_COL . $row)->applyFromArray([
                    'font' => ['size' => 9, 'color' => ['rgb' => '1F2937']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $rowBg]],
                    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E5E7EB']]],
                ]);
                $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('B' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('E' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('F' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('G' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('J' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $this->formatNumberCells($sheet, ['H'], $row);

                if ($detail['sumber'] === 'TU otomatis') {
                    $sheet->getStyle('J' . $row)->getFont()->setBold(true);
                    $sheet->getStyle('J' . $row)->getFont()->getColor()->setRGB('B45309');
                    $sheet->getStyle('J' . $row)->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setRGB('FEF3C7');
                }

                $sheet->getRowDimension($row)->setRowHeight(30);
                $row++;
                $index++;
            }

            $lastDataRow = $row - 1;
            $sheet->mergeCells('A' . $row . ':G' . $row);
            $sheet->setCellValue('A' . $row, 'JUMLAH TOTAL (' . count($details) . ' baris rencana)');
            $sheet->setCellValue('H' . $row, '=SUM(H' . self::FIRST_DATA_ROW . ':H' . $lastDataRow . ')');
            $sheet->setCellValue('I' . $row, '');
            $sheet->setCellValue('J' . $row, '');
            $sheet->getStyle('A' . $row . ':' . self::DETAIL_LAST_COL . $row)->applyFromArray([
                'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::NAVY]],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => 'FFFFFF']]],
            ]);
            $this->formatNumberCells($sheet, ['H'], $row);
            $sheet->getRowDimension($row)->setRowHeight(24);
        }

        $widths = [
            'A' => 5, 'B' => 18, 'C' => 40, 'D' => 40, 'E' => 12,
            'F' => 14, 'G' => 14, 'H' => 17, 'I' => 42, 'J' => 14,
        ];
        foreach ($widths as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        if (!empty($details)) {
            $lastDataRow = self::FIRST_DATA_ROW + count($details) - 1;
            $sheet->setAutoFilter('A' . $headerRow . ':' . self::DETAIL_LAST_COL . $lastDataRow);
        }
        $sheet->freezePane('A' . self::FIRST_DATA_ROW);
        $sheet->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
            ->setPaperSize(PageSetup::PAPERSIZE_A4)
            ->setFitToWidth(1)
            ->setFitToHeight(0)
            ->setRowsToRepeatAtTopByStartAndEnd($headerRow, $headerRow);
        $sheet->getPageMargins()->setTop(0.4)->setBottom(0.4)->setLeft(0.3)->setRight(0.3);
    }

    // ──────────────────────────────────────────────────────────
    // Helper
    // ──────────────────────────────────────────────────────────

    private function formatNumberCells(Worksheet $sheet, array $columns, int $row): void
    {
        foreach ($columns as $col) {
            $sheet->getStyle($col . $row)->getNumberFormat()->setFormatCode(self::NUMBER_FORMAT);
            $sheet->getStyle($col . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }
    }

    /**
     * @return array{0:string,1:string} [warna teks, warna latar]
     */
    private function statusColors(string $status): array
    {
        if ($status === 'Belum ada rencana') {
            return ['92400E', 'FEF3C7'];
        }
        if ($status === 'Selesai' || $status === 'Selesai (sisa 0)') {
            return ['166534', 'DCFCE7'];
        }
        return ['1D4ED8', 'DBEAFE'];
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
