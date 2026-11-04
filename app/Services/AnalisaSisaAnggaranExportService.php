<?php

declare(strict_types=1);

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Title;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Export "Analisa Sisa Anggaran" ke Excel.
 *
 * Sheet 1: daftar rekening + sisa anggaran, kartu ringkasan, dan status.
 * Sheet 2: distribusi sisa, rekap per seksi, top 10 rekening, plus grafik.
 * Sheet 3: rekap pagu/realisasi/sisa/serapan per tagar + grafik.
 */
class AnalisaSisaAnggaranExportService
{
    private const SHEET_MAIN = 'Analisa Sisa';
    private const SHEET_SUMMARY = 'Ringkasan';
    private const SHEET_TAG = 'Rekap Tagar';
    private const LAST_COL = 'L';
    private const HEADER_ROW = 8;
    private const FIRST_DATA_ROW = 9;

    private const NUMBER_FORMAT = '#,##0';
    private const SIGNED_FORMAT = '#,##0;[Red]-#,##0';
    private const PERCENT_FORMAT = '0.0%';

    private const NAVY = '1E3A5F';
    private const ZEBRA = 'F8FAFC';

    public function download(array $report): void
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setCreator('CDK Wilayah Bojonegoro')
            ->setTitle('Analisa Sisa Anggaran ' . (int) ($report['tahun'] ?? date('Y')))
            ->setSubject('Analisa Sisa Anggaran');

        $this->buildMainSheet($spreadsheet->getActiveSheet(), $report);
        $this->buildSummarySheet($spreadsheet->createSheet(), $report);
        $this->buildTagSheet($spreadsheet->createSheet(), $report);

        $spreadsheet->setActiveSheetIndex(0);

        $filename = 'Analisa_Sisa_Anggaran_' . (int) ($report['tahun'] ?? date('Y')) . '.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        header('Pragma: public');

        $writer = new Xlsx($spreadsheet);
        $writer->setIncludeCharts(true);
        $writer->save('php://output');
        exit;
    }

    // ──────────────────────────────────────────────────────────
    // SHEET 1 — Daftar rekening
    // ──────────────────────────────────────────────────────────

    private function buildMainSheet(Worksheet $sheet, array $report): void
    {
        $sheet->setTitle(self::SHEET_MAIN);
        $tahun = (int) ($report['tahun'] ?? date('Y'));

        $this->writeTitle($sheet, $tahun, 'ANALISA SISA ANGGARAN PER REKENING');
        $this->writeFilterInfo($sheet, $report);
        $this->writeSummaryCards($sheet, $report);
        $this->writeTableHeader($sheet);
        $this->writeRows($sheet, $report);
        $this->configureMainSheet($sheet, $report);
    }

    private function writeTitle(Worksheet $sheet, int $tahun, string $title): void
    {
        $sheet->mergeCells('A1:' . self::LAST_COL . '1');
        $sheet->setCellValue('A1', $title . ' TAHUN ANGGARAN ' . $tahun);
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => self::NAVY]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(28);

        $sheet->mergeCells('A2:' . self::LAST_COL . '2');
        $sheet->setCellValue('A2', 'CABANG DINAS KEHUTANAN (CDK) WILAYAH BOJONEGORO');
        $sheet->getStyle('A2')->applyFromArray([
            'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => '374151']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(2)->setRowHeight(20);
    }

    private function writeFilterInfo(Worksheet $sheet, array $report): void
    {
        $filters = $report['filters'] ?? [];
        $seksiMap = $report['seksiMap'] ?? [];
        $seksiId = (int) ($filters['seksi'] ?? 0);
        $minJuta = (float) ($filters['min_juta'] ?? 0);
        $sortLabels = [
            'sisa_desc' => 'Sisa terbesar',
            'sisa_asc' => 'Sisa terkecil',
            'pagu_desc' => 'Pagu terbesar',
            'serapan_asc' => 'Serapan terendah',
            'serapan_desc' => 'Serapan tertinggi',
            'nama_asc' => 'Nama rekening (A–Z)',
        ];

        $parts = [
            'Seksi: ' . ($seksiId > 0 ? ($seksiMap[$seksiId] ?? ('#' . $seksiId)) : 'Semua seksi'),
            'Sisa minimal: ' . ($minJuta > 0 ? 'Rp ' . number_format($minJuta * 1_000_000, 0, ',', '.') : 'Semua'),
            'Urutan: ' . ($sortLabels[$filters['sort'] ?? 'sisa_desc'] ?? 'Sisa terbesar'),
        ];
        if (trim((string) ($filters['q'] ?? '')) !== '') {
            $parts[] = 'Pencarian: "' . $filters['q'] . '"';
        }
        if (!empty($filters['show_zero'])) {
            $parts[] = 'Rekening tanpa sisa: ditampilkan';
        }
        $tagar = trim((string) ($filters['tagar'] ?? ''));
        if ($tagar !== '') {
            $parts[] = 'Tagar: ' . ($tagar === '__none__' ? 'Tanpa tagar' : $tagar);
        }

        $sheet->mergeCells('A3:' . self::LAST_COL . '3');
        $sheet->setCellValue('A3', implode('   •   ', $parts));
        $sheet->getStyle('A3')->applyFromArray([
            'font' => ['size' => 9, 'italic' => true, 'color' => ['rgb' => '6B7280']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(3)->setRowHeight(18);
    }

    private function writeSummaryCards(Worksheet $sheet, array $report): void
    {
        $cards = [
            ['A', 'B', 'TOTAL PAGU', (float) ($report['totalPagu'] ?? 0), self::NUMBER_FORMAT, 'EEF2FF', self::NAVY],
            ['C', 'D', 'REALISASI', (float) ($report['totalRealisasi'] ?? 0), self::NUMBER_FORMAT, 'E0F2FE', '0C4A6E'],
            ['E', 'F', 'SISA ANGGARAN', (float) ($report['totalSisa'] ?? 0), self::NUMBER_FORMAT, 'DCFCE7', '166534'],
            ['G', 'H', 'SERAPAN', ((float) ($report['serapanPersen'] ?? 0)) / 100, self::PERCENT_FORMAT, 'FEF3C7', '92400E'],
            ['I', 'J', 'JUMLAH REKENING', (int) ($report['total'] ?? 0), self::NUMBER_FORMAT, 'F1F5F9', '334155'],
            ['K', 'L', 'REKENING BERSISA', (int) ($report['sisaCount'] ?? 0), self::NUMBER_FORMAT, 'F1F5F9', '334155'],
        ];

        foreach ($cards as [$startCol, $endCol, $label, $value, $format, $bg, $fontColor]) {
            $labelRange = $startCol . '5:' . $endCol . '5';
            $valueRange = $startCol . '6:' . $endCol . '6';

            $sheet->mergeCells($labelRange);
            $sheet->mergeCells($valueRange);

            $sheet->setCellValue($startCol . '5', $label);
            $sheet->setCellValue($startCol . '6', $value);

            $sheet->getStyle($labelRange)->applyFromArray([
                'font' => ['bold' => true, 'size' => 8, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::NAVY]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'FFFFFF']]],
            ]);

            $sheet->getStyle($valueRange)->applyFromArray([
                'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => $fontColor]],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bg]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D1D5DB']]],
            ]);
            $sheet->getStyle($startCol . '6')->getNumberFormat()->setFormatCode($format);
        }

        $sheet->getRowDimension(5)->setRowHeight(18);
        $sheet->getRowDimension(6)->setRowHeight(26);
        $sheet->getRowDimension(7)->setRowHeight(8);
    }

    private function writeTableHeader(Worksheet $sheet): void
    {
        $headers = [
            'A' => 'NO',
            'B' => 'KODE REKENING',
            'C' => 'NAMA REKENING',
            'D' => 'PROGRAM',
            'E' => 'SUB KEGIATAN',
            'F' => 'SEKSI',
            'G' => "PAGU\n(Rp)",
            'H' => "REALISASI\n(Rp)",
            'I' => "SISA\n(Rp)",
            'J' => "SERAPAN\n(%)",
            'K' => 'STATUS',
            'L' => 'VISUAL SERAPAN',
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
        $sheet->getRowDimension(self::HEADER_ROW)->setRowHeight(32);
    }

    private function writeRows(Worksheet $sheet, array $report): void
    {
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
            $this->writeFooter($sheet, $row + 2);

            return;
        }

        $index = 1;
        foreach ($rows as $data) {
            $sisa = (float) $data['sisa'];
            $serapan = (float) $data['serapan'];
            $isZebra = ($index % 2) === 0;
            $rowBg = $isZebra ? self::ZEBRA : 'FFFFFF';

            $sheet->setCellValue('A' . $row, $index);
            $sheet->setCellValue('B' . $row, (string) $data['kode_rekening']);
            $sheet->setCellValue('C' . $row, (string) $data['nama_rekening']);
            $sheet->setCellValue('D' . $row, (string) $data['nama_program']);
            $sheet->setCellValue('E' . $row, trim((string) $data['kode_sub_kegiatan'] . ' ' . $data['nama_sub_kegiatan']));
            $sheet->setCellValue('F' . $row, (string) $data['nama_seksi']);
            $sheet->setCellValue('G' . $row, (float) $data['pagu']);
            $sheet->setCellValue('H' . $row, (float) $data['realisasi']);
            $sheet->setCellValue('I' . $row, $sisa);
            $sheet->setCellValue('J' . $row, $serapan / 100);

            [$status, $statusColor, $statusBg] = $this->statusFor($sisa);
            $sheet->setCellValue('K' . $row, $status);

            $barLength = max(0, min(20, (int) round($serapan / 5)));
            if ($serapan > 0 && $barLength === 0) {
                $barLength = 1;
            }
            $sheet->setCellValue('L' . $row, str_repeat('█', $barLength));

            $sheet->getStyle('A' . $row . ':' . self::LAST_COL . $row)->applyFromArray([
                'font' => ['size' => 9, 'color' => ['rgb' => '1F2937']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $rowBg]],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E5E7EB']]],
            ]);

            // Kolom angka
            foreach (['G', 'H', 'I'] as $col) {
                $sheet->getStyle($col . $row)->getNumberFormat()->setFormatCode(self::NUMBER_FORMAT);
                $sheet->getStyle($col . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            }
            $sheet->getStyle('J' . $row)->getNumberFormat()->setFormatCode(self::PERCENT_FORMAT);
            $sheet->getStyle('J' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            // Sisa: tebal & berwarna
            $sheet->getStyle('I' . $row)->getFont()->setBold(true);
            $sheet->getStyle('I' . $row)->getFont()->getColor()->setRGB($sisa < 0 ? 'B91C1C' : '166534');
            $sheet->getStyle('I' . $row)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB($sisa < 0 ? 'FEE2E2' : 'F0FDF4');

            // Status
            $sheet->getStyle('K' . $row)->getFont()->setBold(true);
            $sheet->getStyle('K' . $row)->getFont()->getColor()->setRGB($statusColor);
            $sheet->getStyle('K' . $row)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB($statusBg);
            $sheet->getStyle('K' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            // Visual bar: warna mengikuti tingkat serapan
            $barColor = $serapan > 100 ? 'B91C1C' : ($serapan >= 80 ? '15803D' : ($serapan >= 40 ? 'B45309' : '6B7280'));
            $sheet->getStyle('L' . $row)->getFont()->getColor()->setRGB($barColor);
            $sheet->getStyle('L' . $row)->getFont()->setSize(8);
            $sheet->getStyle('L' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

            $sheet->getRowDimension($row)->setRowHeight(32);
            $row++;
            $index++;
        }

        $lastDataRow = $row - 1;
        $this->writeTotalRow($sheet, $row, $report, $lastDataRow);
        $this->writeFooter($sheet, $row + 2);
    }

    private function writeTotalRow(Worksheet $sheet, int $row, array $report, int $lastDataRow): void
    {
        $sheet->mergeCells('A' . $row . ':F' . $row);
        $sheet->setCellValue('A' . $row, 'JUMLAH TOTAL (' . count($report['rows'] ?? []) . ' rekening)');
        $sheet->setCellValue('G' . $row, '=SUM(G' . self::FIRST_DATA_ROW . ':G' . $lastDataRow . ')');
        $sheet->setCellValue('H' . $row, '=SUM(H' . self::FIRST_DATA_ROW . ':H' . $lastDataRow . ')');
        $sheet->setCellValue('I' . $row, '=SUM(I' . self::FIRST_DATA_ROW . ':I' . $lastDataRow . ')');
        $sheet->setCellValue('J' . $row, '=IF(G' . $row . '=0,0,H' . $row . '/G' . $row . ')');
        $sheet->setCellValue('K' . $row, '');
        $sheet->setCellValue('L' . $row, '');

        $sheet->getStyle('A' . $row . ':' . self::LAST_COL . $row)->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::NAVY]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'horizontal' => Alignment::HORIZONTAL_LEFT],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => 'FFFFFF']]],
        ]);

        foreach (['G', 'H', 'I'] as $col) {
            $sheet->getStyle($col . $row)->getNumberFormat()->setFormatCode(self::NUMBER_FORMAT);
            $sheet->getStyle($col . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }
        $sheet->getStyle('J' . $row)->getNumberFormat()->setFormatCode(self::PERCENT_FORMAT);
        $sheet->getStyle('J' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getRowDimension($row)->setRowHeight(24);
    }

    private function writeFooter(Worksheet $sheet, int $row): void
    {
        $sheet->mergeCells('A' . $row . ':' . self::LAST_COL . $row);
        $sheet->setCellValue(
            'A' . $row,
            'Sisa = Pagu − Realisasi terverifikasi. Dicetak ' . date('d-m-Y H:i') . ' dari Dashboard Anggaran CDK Wilayah Bojonegoro.'
        );
        $sheet->getStyle('A' . $row)->applyFromArray([
            'font' => ['size' => 8, 'italic' => true, 'color' => ['rgb' => '6B7280']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
    }

    private function configureMainSheet(Worksheet $sheet, array $report): void
    {
        $widths = [
            'A' => 5,
            'B' => 20,
            'C' => 44,
            'D' => 36,
            'E' => 40,
            'F' => 22,
            'G' => 17,
            'H' => 17,
            'I' => 17,
            'J' => 10,
            'K' => 20,
            'L' => 22,
        ];
        foreach ($widths as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        $rows = $report['rows'] ?? [];
        $lastDataRow = self::FIRST_DATA_ROW + max(0, count($rows) - 1);
        if (!empty($rows)) {
            $sheet->setAutoFilter('A' . self::HEADER_ROW . ':' . self::LAST_COL . $lastDataRow);
        }

        $sheet->freezePane('A' . self::FIRST_DATA_ROW);
        $sheet->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
            ->setPaperSize(PageSetup::PAPERSIZE_A4)
            ->setFitToWidth(1)
            ->setFitToHeight(0)
            ->setRowsToRepeatAtTopByStartAndEnd(self::HEADER_ROW, self::HEADER_ROW);
        $sheet->getPageMargins()
            ->setTop(0.4)
            ->setBottom(0.4)
            ->setLeft(0.3)
            ->setRight(0.3);
    }

    // ──────────────────────────────────────────────────────────
    // SHEET 2 — Ringkasan, distribusi, grafik
    // ──────────────────────────────────────────────────────────

    private function buildSummarySheet(Worksheet $sheet, array $report): void
    {
        $sheet->setTitle(self::SHEET_SUMMARY);
        $tahun = (int) ($report['tahun'] ?? date('Y'));

        $sheet->mergeCells('A1:K1');
        $sheet->setCellValue('A1', 'RINGKASAN ANALISA SISA ANGGARAN ' . $tahun);
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => self::NAVY]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(26);

        $sheet->mergeCells('A2:K2');
        $sheet->setCellValue('A2', 'CABANG DINAS KEHUTANAN (CDK) WILAYAH BOJONEGORO');
        $sheet->getStyle('A2')->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => '374151']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // 1) Distribusi sisa
        $row = 4;
        $bucketStart = $this->writeSectionHeader($sheet, $row, 'DISTRIBUSI SISA ANGGARAN', 'D');
        $headerRow = $bucketStart + 1;
        $this->writeSmallHeader($sheet, $headerRow, ['A' => 'NO', 'B' => 'RENTANG SISA', 'C' => 'JUMLAH REKENING', 'D' => 'TOTAL SISA (Rp)']);
        $row = $headerRow + 1;
        $no = 1;
        foreach ($report['bucketList'] ?? [] as $bucket) {
            $sheet->setCellValue('A' . $row, $no);
            $sheet->setCellValue('B' . $row, (string) $bucket['label']);
            $sheet->setCellValue('C' . $row, (int) $bucket['count']);
            $sheet->setCellValue('D' . $row, (float) $bucket['total']);
            $isOver = ($bucket['label'] ?? '') === 'Over realisasi';
            $this->styleSummaryDataRow($sheet, $row, ['A', 'B', 'C', 'D'], 'D', $isOver);
            $sheet->getStyle('C' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $row++;
            $no++;
        }
        $bucketEnd = $row - 1;

        // 2) Per seksi
        $row += 1;
        $seksiSectionRow = $row;
        $seksiHeaderRow = $this->writeSectionHeader($sheet, $row, 'SISA ANGGARAN PER SEKSI', 'D') + 1;
        $this->writeSmallHeader($sheet, $seksiHeaderRow, ['A' => 'NO', 'B' => 'SEKSI', 'C' => 'JUMLAH REKENING', 'D' => 'TOTAL SISA (Rp)']);
        $seksiStart = $seksiHeaderRow + 1;
        $row = $seksiStart;
        $no = 1;
        $seksiAgg = $report['seksiAgg'] ?? [];
        foreach ($seksiAgg as $seksi) {
            $sheet->setCellValue('A' . $row, $no);
            $sheet->setCellValue('B' . $row, (string) $seksi['nama']);
            $sheet->setCellValue('C' . $row, (int) $seksi['count']);
            $sheet->setCellValue('D' . $row, (float) $seksi['sisa']);
            $this->styleSummaryDataRow($sheet, $row, ['A', 'B', 'C', 'D'], 'D', false);
            $sheet->getStyle('C' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $row++;
            $no++;
        }
        $seksiEnd = $row - 1;
        if (empty($seksiAgg)) {
            $sheet->mergeCells('A' . $row . ':D' . $row);
            $sheet->setCellValue('A' . $row, 'Belum ada sisa anggaran positif per seksi.');
            $sheet->getStyle('A' . $row . ':D' . $row)->applyFromArray([
                'font' => ['italic' => true, 'size' => 9, 'color' => ['rgb' => '6B7280']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D1D5DB']]],
            ]);
            $seksiEnd = $row;
            $row++;
        }

        // 3) Top 10 rekening
        $row += 1;
        $topStart = $this->writeSectionHeader($sheet, $row, '10 REKENING DENGAN SISA TERBESAR', 'E') + 1;
        $this->writeSmallHeader($sheet, $topStart, [
            'A' => 'NO',
            'B' => 'KODE REKENING',
            'C' => 'NAMA REKENING',
            'D' => 'SEKSI',
            'E' => 'SISA (Rp)',
        ]);
        $topDataStart = $topStart + 1;
        $row = $topDataStart;
        $no = 1;
        $topRows = $report['topRows'] ?? [];
        foreach ($topRows as $top) {
            $sheet->setCellValue('A' . $row, $no);
            $sheet->setCellValue('B' . $row, (string) $top['kode_rekening']);
            $sheet->setCellValue('C' . $row, (string) $top['nama_rekening']);
            $sheet->setCellValue('D' . $row, (string) $top['nama_seksi']);
            $sheet->setCellValue('E' . $row, (float) $top['sisa']);
            $this->styleSummaryDataRow($sheet, $row, ['A', 'B', 'C', 'D', 'E'], 'E', false);
            $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $row++;
            $no++;
        }
        $topDataEnd = $row - 1;

        if (empty($topRows)) {
            $sheet->mergeCells('A' . $row . ':E' . $row);
            $sheet->setCellValue('A' . $row, 'Belum ada rekening dengan sisa positif.');
            $sheet->getStyle('A' . $row . ':E' . $row)->applyFromArray([
                'font' => ['italic' => true, 'size' => 9, 'color' => ['rgb' => '6B7280']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D1D5DB']]],
            ]);
            $topDataEnd = $row;
        }

        // Lebar kolom
        $sheet->getColumnDimension('A')->setWidth(5);
        $sheet->getColumnDimension('B')->setWidth(24);
        $sheet->getColumnDimension('C')->setWidth(46);
        $sheet->getColumnDimension('D')->setWidth(26);
        $sheet->getColumnDimension('E')->setWidth(18);

        // Grafik
        $this->addCharts($sheet, $topRows, $topDataStart, $topDataEnd, $seksiAgg, $seksiStart, $seksiEnd);

        $sheet->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
            ->setPaperSize(PageSetup::PAPERSIZE_A4)
            ->setFitToWidth(1)
            ->setFitToHeight(0);
    }

    // ──────────────────────────────────────────────────────────
    // SHEET 3 — Rekap per tagar
    // ──────────────────────────────────────────────────────────

    private function writeTagSummaryCards(Worksheet $sheet, array $tagRecap): void
    {
        $pagu = 0.0;
        $realisasi = 0.0;
        foreach ($tagRecap as $tag) {
            $pagu += (float) $tag['pagu'];
            $realisasi += (float) $tag['realisasi'];
        }
        $sisa = $pagu - $realisasi;
        $serapan = $pagu > 0 ? $realisasi / $pagu : 0.0;

        $cards = [
            ['A', 'B', 'TOTAL PAGU', $pagu, self::NUMBER_FORMAT, 'EEF2FF', self::NAVY],
            ['C', 'D', 'REALISASI', $realisasi, self::NUMBER_FORMAT, 'E0F2FE', '0C4A6E'],
            ['E', 'F', 'SISA ANGGARAN', $sisa, self::NUMBER_FORMAT, 'DCFCE7', '166534'],
            ['G', 'H', 'SERAPAN', $serapan, self::PERCENT_FORMAT, 'FEF3C7', '92400E'],
        ];

        foreach ($cards as [$startCol, $endCol, $label, $value, $format, $bg, $fontColor]) {
            $labelRange = $startCol . '5:' . $endCol . '5';
            $valueRange = $startCol . '6:' . $endCol . '6';

            $sheet->mergeCells($labelRange);
            $sheet->mergeCells($valueRange);
            $sheet->setCellValue($startCol . '5', $label);
            $sheet->setCellValue($startCol . '6', $value);

            $sheet->getStyle($labelRange)->applyFromArray([
                'font' => ['bold' => true, 'size' => 8, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::NAVY]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'FFFFFF']]],
            ]);
            $sheet->getStyle($valueRange)->applyFromArray([
                'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => $fontColor]],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bg]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D1D5DB']]],
            ]);
            $sheet->getStyle($startCol . '6')->getNumberFormat()->setFormatCode($format);
        }

        $sheet->getRowDimension(5)->setRowHeight(18);
        $sheet->getRowDimension(6)->setRowHeight(26);
        $sheet->getRowDimension(7)->setRowHeight(8);
    }

    private function buildTagSheet(Worksheet $sheet, array $report): void
    {
        $sheet->setTitle(self::SHEET_TAG);
        $tahun = (int) ($report['tahun'] ?? date('Y'));
        $tagRecap = $report['tagRecap'] ?? [];

        $headerRow = 8;
        $firstRow = 9;

        $this->writeTitle($sheet, $tahun, 'REKAP REALISASI PER TAGAR');
        $this->writeFilterInfo($sheet, $report);
        $this->writeTagSummaryCards($sheet, $tagRecap);

        // Catatan: rekap tagar sengaja mengabaikan filter tagar.
        $sheet->mergeCells('A4:G4');
        $sheet->setCellValue('A4', 'Catatan: rekap ini selalu menampilkan semua tagar; filter tagar hanya memengaruhi sheet "Analisa Sisa".');
        $sheet->getStyle('A4')->applyFromArray([
            'font' => ['size' => 8, 'italic' => true, 'color' => ['rgb' => '6B7280']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(4)->setRowHeight(16);

        $headers = [
            'A' => 'NO',
            'B' => 'TAGAR',
            'C' => "JUMLAH\nREKENING",
            'D' => "PAGU\n(Rp)",
            'E' => "REALISASI\n(Rp)",
            'F' => "SISA\n(Rp)",
            'G' => "SERAPAN\n(%)",
        ];
        foreach ($headers as $col => $label) {
            $sheet->setCellValue($col . $headerRow, $label);
        }
        $sheet->getStyle('A' . $headerRow . ':G' . $headerRow)->applyFromArray([
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

        if (empty($tagRecap)) {
            $sheet->mergeCells('A' . $firstRow . ':G' . $firstRow);
            $sheet->setCellValue('A' . $firstRow, 'Belum ada data tagar pada filter ini.');
            $sheet->getStyle('A' . $firstRow . ':G' . $firstRow)->applyFromArray([
                'font' => ['italic' => true, 'size' => 10, 'color' => ['rgb' => '6B7280']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D1D5DB']]],
            ]);
            $sheet->getRowDimension($firstRow)->setRowHeight(24);
            $this->writeFooter($sheet, $firstRow + 2);
        } else {
            $row = $firstRow;
            $no = 1;
            foreach ($tagRecap as $tag) {
                $sisa = (float) $tag['sisa'];
                $serapan = (float) $tag['serapan'];
                $isNone = ($tag['tagar'] ?? '') === '__none__';
                $rowBg = ($no % 2) === 0 ? self::ZEBRA : 'FFFFFF';

                $sheet->setCellValue('A' . $row, $no);
                $sheet->setCellValue('B' . $row, (string) $tag['label']);
                $sheet->setCellValue('C' . $row, (int) $tag['count']);
                $sheet->setCellValue('D' . $row, (float) $tag['pagu']);
                $sheet->setCellValue('E' . $row, (float) $tag['realisasi']);
                $sheet->setCellValue('F' . $row, $sisa);
                $sheet->setCellValue('G' . $row, $serapan / 100);

                $sheet->getStyle('A' . $row . ':G' . $row)->applyFromArray([
                    'font' => ['size' => 9, 'color' => ['rgb' => '1F2937']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $rowBg]],
                    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E5E7EB']]],
                ]);

                foreach (['D', 'E', 'F'] as $col) {
                    $sheet->getStyle($col . $row)->getNumberFormat()->setFormatCode(self::NUMBER_FORMAT);
                    $sheet->getStyle($col . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                }
                $sheet->getStyle('G' . $row)->getNumberFormat()->setFormatCode(self::PERCENT_FORMAT);
                $sheet->getStyle('G' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('C' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Sisa diberi warna seperti sheet utama.
                $sheet->getStyle('F' . $row)->getFont()->setBold(true);
                $sheet->getStyle('F' . $row)->getFont()->getColor()->setRGB($sisa < 0 ? 'B91C1C' : '166534');
                $sheet->getStyle('F' . $row)->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB($sisa < 0 ? 'FEE2E2' : 'F0FDF4');

                // Tagar sebagai badge: hijau untuk tag, abu untuk tanpa tagar.
                $sheet->getStyle('B' . $row)->getFont()->setBold(true);
                $sheet->getStyle('B' . $row)->getFont()->getColor()->setRGB($isNone ? '475569' : '065F46');
                $sheet->getStyle('B' . $row)->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB($isNone ? 'F1F5F9' : 'ECFDF5');

                $sheet->getRowDimension($row)->setRowHeight(22);
                $row++;
                $no++;
            }

            $lastDataRow = $row - 1;
            $sheet->mergeCells('A' . $row . ':B' . $row);
            $sheet->setCellValue('A' . $row, 'JUMLAH TOTAL (' . count($tagRecap) . ' tagar)');
            $sheet->setCellValue('C' . $row, '=SUM(C' . $firstRow . ':C' . $lastDataRow . ')');
            $sheet->setCellValue('D' . $row, '=SUM(D' . $firstRow . ':D' . $lastDataRow . ')');
            $sheet->setCellValue('E' . $row, '=SUM(E' . $firstRow . ':E' . $lastDataRow . ')');
            $sheet->setCellValue('F' . $row, '=SUM(F' . $firstRow . ':F' . $lastDataRow . ')');
            $sheet->setCellValue('G' . $row, '=IF(D' . $row . '=0,0,E' . $row . '/D' . $row . ')');

            $sheet->getStyle('A' . $row . ':G' . $row)->applyFromArray([
                'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::NAVY]],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'horizontal' => Alignment::HORIZONTAL_LEFT],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => 'FFFFFF']]],
            ]);
            foreach (['D', 'E', 'F'] as $col) {
                $sheet->getStyle($col . $row)->getNumberFormat()->setFormatCode(self::NUMBER_FORMAT);
                $sheet->getStyle($col . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            }
            $sheet->getStyle('G' . $row)->getNumberFormat()->setFormatCode(self::PERCENT_FORMAT);
            $sheet->getStyle('G' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('C' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getRowDimension($row)->setRowHeight(24);

            $this->writeFooter($sheet, $row + 2);
            $this->addTagChart($sheet, $tagRecap, $firstRow, $lastDataRow);
        }

        $widths = [
            'A' => 5,
            'B' => 34,
            'C' => 13,
            'D' => 17,
            'E' => 17,
            'F' => 17,
            'G' => 10,
        ];
        foreach ($widths as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }
        if (!empty($tagRecap)) {
            $lastDataRow = $firstRow + count($tagRecap) - 1;
            $sheet->setAutoFilter('A' . $headerRow . ':G' . $lastDataRow);
        }

        $sheet->freezePane('A' . $firstRow);
        $sheet->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
            ->setPaperSize(PageSetup::PAPERSIZE_A4)
            ->setFitToWidth(1)
            ->setFitToHeight(0)
            ->setRowsToRepeatAtTopByStartAndEnd($headerRow, $headerRow);
        $sheet->getPageMargins()
            ->setTop(0.4)
            ->setBottom(0.4)
            ->setLeft(0.3)
            ->setRight(0.3);
    }

    private function addTagChart(Worksheet $sheet, array $tagRecap, int $start, int $end): void
    {
        $count = $end - $start + 1;
        if ($count <= 0) {
            return;
        }

        $sheetRef = "'" . self::SHEET_TAG . "'";
        $labels = array_map(static fn($t) => (string) $t['label'], $tagRecap);
        $paguValues = array_map(static fn($t) => (float) $t['pagu'], $tagRecap);
        $realisasiValues = array_map(static fn($t) => (float) $t['realisasi'], $tagRecap);

        $categories = new DataSeriesValues(
            DataSeriesValues::DATASERIES_TYPE_STRING,
            $sheetRef . '!$B$' . $start . ':$B$' . $end,
            null,
            $count,
            $labels
        );
        $paguSeries = new DataSeriesValues(
            DataSeriesValues::DATASERIES_TYPE_NUMBER,
            $sheetRef . '!$D$' . $start . ':$D$' . $end,
            self::NUMBER_FORMAT,
            $count,
            $paguValues,
            null,
            '4338CA'
        );
        $realisasiSeries = new DataSeriesValues(
            DataSeriesValues::DATASERIES_TYPE_NUMBER,
            $sheetRef . '!$E$' . $start . ':$E$' . $end,
            self::NUMBER_FORMAT,
            $count,
            $realisasiValues,
            null,
            '059669'
        );

        $series = new DataSeries(
            DataSeries::TYPE_BARCHART,
            DataSeries::GROUPING_CLUSTERED,
            [0, 1],
            [],
            [$categories],
            [$paguSeries, $realisasiSeries],
            DataSeries::DIRECTION_BAR
        );
        $chart = new Chart(
            'chartRekapTagar',
            new Title('Pagu vs Realisasi per Tagar'),
            null,
            new PlotArea(null, [$series]),
            true,
            DataSeries::EMPTY_AS_GAP,
            null,
            new Title('Rupiah')
        );
        $chart->setTopLeftPosition('I8');
        $chart->setBottomRightPosition('P28');
        $sheet->addChart($chart);
    }

    private function writeSectionHeader(Worksheet $sheet, int $row, string $title, string $lastCol): int
    {
        $sheet->mergeCells('A' . $row . ':' . $lastCol . $row);
        $sheet->setCellValue('A' . $row, $title);
        $sheet->getStyle('A' . $row . ':' . $lastCol . $row)->applyFromArray([
            'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::NAVY]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension($row)->setRowHeight(22);

        return $row;
    }

    /**
     * @param array<string,string> $headers
     */
    private function writeSmallHeader(Worksheet $sheet, int $row, array $headers): void
    {
        foreach ($headers as $col => $label) {
            $sheet->setCellValue($col . $row, $label);
        }
        $lastCol = array_key_last($headers);
        $sheet->getStyle('A' . $row . ':' . $lastCol . $row)->applyFromArray([
            'font' => ['bold' => true, 'size' => 9, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '475569']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'FFFFFF']]],
        ]);
        $sheet->getRowDimension($row)->setRowHeight(24);
    }

    /**
     * @param string[] $columns
     */
    private function styleSummaryDataRow(Worksheet $sheet, int $row, array $columns, string $numberCol, bool $negative): void
    {
        $lastCol = end($columns);
        $sheet->getStyle('A' . $row . ':' . $lastCol . $row)->applyFromArray([
            'font' => ['size' => 9, 'color' => ['rgb' => '1F2937']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFFFF']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E5E7EB']]],
        ]);
        $sheet->getStyle($numberCol . $row)->getNumberFormat()->setFormatCode(self::SIGNED_FORMAT);
        $sheet->getStyle($numberCol . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        if ($negative) {
            $sheet->getStyle($numberCol . $row)->getFont()->getColor()->setRGB('B91C1C');
        }
        $sheet->getRowDimension($row)->setRowHeight(20);
    }

    private function addCharts(
        Worksheet $sheet,
        array $topRows,
        int $topStart,
        int $topEnd,
        array $seksiAgg,
        int $seksiStart,
        int $seksiEnd
    ): void {
        $sheetRef = "'" . self::SHEET_SUMMARY . "'";

        if (!empty($topRows) && $topEnd >= $topStart) {
            $count = $topEnd - $topStart + 1;
            $topNames = array_map(static fn($r) => (string) $r['nama_rekening'], $topRows);
            $topValues = array_map(static fn($r) => (float) $r['sisa'], $topRows);
            $categories = new DataSeriesValues(
                DataSeriesValues::DATASERIES_TYPE_STRING,
                $sheetRef . '!$C$' . $topStart . ':$C$' . $topEnd,
                null,
                $count,
                $topNames
            );
            $values = new DataSeriesValues(
                DataSeriesValues::DATASERIES_TYPE_NUMBER,
                $sheetRef . '!$E$' . $topStart . ':$E$' . $topEnd,
                self::NUMBER_FORMAT,
                $count,
                $topValues,
                null,
                '4338CA'
            );
            $series = new DataSeries(
                DataSeries::TYPE_BARCHART,
                DataSeries::GROUPING_CLUSTERED,
                [0],
                [],
                [$categories],
                [$values],
                DataSeries::DIRECTION_BAR
            );
            $chart = new Chart(
                'chartSisaTop10',
                new Title('10 Rekening dengan Sisa Terbesar'),
                null,
                new PlotArea(null, [$series]),
                true,
                DataSeries::EMPTY_AS_GAP,
                null,
                new Title('Sisa (Rp)')
            );
            $chart->setTopLeftPosition('G4');
            $chart->setBottomRightPosition('N22');
            $sheet->addChart($chart);
        }

        if (!empty($seksiAgg) && $seksiEnd >= $seksiStart) {
            $count = $seksiEnd - $seksiStart + 1;
            $seksiNames = array_map(static fn($s) => (string) $s['nama'], $seksiAgg);
            $seksiValues = array_map(static fn($s) => (float) $s['sisa'], $seksiAgg);
            $categories = new DataSeriesValues(
                DataSeriesValues::DATASERIES_TYPE_STRING,
                $sheetRef . '!$B$' . $seksiStart . ':$B$' . $seksiEnd,
                null,
                $count,
                $seksiNames
            );
            $values = new DataSeriesValues(
                DataSeriesValues::DATASERIES_TYPE_NUMBER,
                $sheetRef . '!$D$' . $seksiStart . ':$D$' . $seksiEnd,
                self::NUMBER_FORMAT,
                $count,
                $seksiValues,
                null,
                '059669'
            );
            $series = new DataSeries(
                DataSeries::TYPE_BARCHART,
                DataSeries::GROUPING_CLUSTERED,
                [0],
                [],
                [$categories],
                [$values],
                DataSeries::DIRECTION_BAR
            );
            $chart = new Chart(
                'chartSisaSeksi',
                new Title('Sisa Anggaran per Seksi'),
                null,
                new PlotArea(null, [$series]),
                true,
                DataSeries::EMPTY_AS_GAP,
                null,
                new Title('Sisa (Rp)')
            );
            $chart->setTopLeftPosition('G24');
            $chart->setBottomRightPosition('N40');
            $sheet->addChart($chart);
        }
    }

    /**
     * @return array{0:string,1:string,2:string} [label, fontColor, bgColor]
     */
    private function statusFor(float $sisa): array
    {
        if ($sisa < 0) {
            return ['Over realisasi', 'B91C1C', 'FEE2E2'];
        }
        if ($sisa == 0.0) {
            return ['Tanpa sisa', '475569', 'F1F5F9'];
        }
        if ($sisa >= 1_000_000_000) {
            return ['Sisa sangat besar', '166534', 'DCFCE7'];
        }
        if ($sisa >= 500_000_000) {
            return ['Sisa besar', '92400E', 'FEF3C7'];
        }
        if ($sisa >= 100_000_000) {
            return ['Perlu percepatan', '1D4ED8', 'E0F2FE'];
        }
        return ['Normal', '475569', 'F1F5F9'];
    }
}
