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
 * Export Excel Laporan Pertanggungjawaban Limpahan UP.
 */
class LpjLimpahanUpExportService
{
    private const LAST_COL = 'F';
    private const NUMBER_FORMAT = '"Rp"#,##0.00';

    /**
     * @param array<string,mixed> $report Hasil LpjLimpahanUpService::build().
     */
    public function download(array $report): void
    {
        $spreadsheet = $this->createSpreadsheet($report);
        $filename = $this->filename($report);

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        header('Pragma: public');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    /**
     * @param array<string,mixed> $report Hasil LpjLimpahanUpService::build().
     */
    public function createSpreadsheet(array $report): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('LPJ Limpahan UP');

        $this->writeHeader($sheet, $report);

        $headerRow = 6;
        $this->writeTableHeader($sheet, $headerRow);

        $row = $headerRow + 1;
        foreach ($report['rows'] ?? [] as $node) {
            $this->writeNode($sheet, $row, $node);
            $row++;
        }

        if ($row === $headerRow + 1) {
            $sheet->mergeCells('A' . $row . ':' . self::LAST_COL . $row);
            $sheet->setCellValue('A' . $row, 'Belum ada transaksi yang diajukan untuk SPJ GU pada periode ini.');
            $sheet->getStyle('A' . $row . ':' . self::LAST_COL . $row)->applyFromArray([
                'font' => ['italic' => true, 'color' => ['rgb' => '666666']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CCCCCC']]],
            ]);
            $row++;
        }

        $this->writeTotalRow($sheet, $row, $report['totals'] ?? []);

        $sheet->getColumnDimension('A')->setWidth(24);
        $sheet->getColumnDimension('B')->setWidth(55);
        foreach (['C', 'D', 'E', 'F'] as $col) {
            $sheet->getColumnDimension($col)->setWidth(20);
        }

        $sheet->freezePane('A7');
        $sheet->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
            ->setPaperSize(PageSetup::PAPERSIZE_A4)
            ->setFitToWidth(1)
            ->setFitToHeight(0)
            ->setRowsToRepeatAtTopByStartAndEnd(6, 6);
        $sheet->getPageMargins()
            ->setTop(0.4)
            ->setBottom(0.4)
            ->setLeft(0.3)
            ->setRight(0.3);

        return $spreadsheet;
    }

    /**
     * @param array<string,mixed> $report
     */
    private function writeHeader(Worksheet $sheet, array $report): void
    {
        $meta = $report['meta'] ?? [];

        $sheet->mergeCells('A1:F1');
        $sheet->setCellValue('A1', (string) ($report['judul'] ?? 'LAPORAN PERTANGGUNGJAWABAN LIMPAHAN UANG PERSEDIAAN'));
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(24);

        $sheet->mergeCells('A2:F2');
        $sheet->setCellValue('A2', 'CDK WILAYAH BOJONEGORO');
        $sheet->getStyle('A2')->applyFromArray([
            'font' => ['bold' => true, 'size' => 11],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $info = [];
        if (!empty($meta['nomor_pengajuan'])) {
            $info[] = 'Nomor: ' . $meta['nomor_pengajuan'];
        }
        if (!empty($meta['tanggal_pengajuan'])) {
            $info[] = 'Tanggal: ' . date('d/m/Y', strtotime((string) $meta['tanggal_pengajuan']));
        }
        if (!empty($meta['status_pengajuan'])) {
            $info[] = 'Status: ' . ucfirst((string) $meta['status_pengajuan']);
        }
        if (empty($info)) {
            $info[] = 'Periode: ' . ($meta['periode_label'] ?? 'Seluruh periode');
        }

        $sheet->mergeCells('A3:F3');
        $sheet->setCellValue('A3', implode('  |  ', $info));
        $sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $info2 = ['Tahun Anggaran: ' . ($meta['tahun'] ?? date('Y'))];
        if (!empty($meta['keterangan'])) {
            $info2[] = 'Keterangan: ' . $meta['keterangan'];
        }
        if (!empty($meta['nomor_pengajuan'])) {
            $info2[] = 'Periode: ' . ($meta['periode_label'] ?? '');
        }
        $sheet->mergeCells('A4:F4');
        $sheet->setCellValue('A4', implode('  |  ', array_filter($info2)));
        $sheet->getStyle('A4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A1:F4')->getFont()->setName('Arial');
    }

    private function writeTableHeader(Worksheet $sheet, int $row): void
    {
        $headers = [
            'A' => 'Kode Rekening',
            'B' => 'Uraian',
            'C' => 'Jumlah Anggaran',
            'D' => 'Belanja Periode Ini',
            'E' => 'Akumulasi Belanja',
            'F' => 'Sisa Anggaran',
        ];

        foreach ($headers as $col => $label) {
            $sheet->setCellValue($col . $row, $label);
        }

        $sheet->getStyle('A' . $row . ':F' . $row)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F4E79']],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
        ]);
        $sheet->getRowDimension($row)->setRowHeight(32);
    }

    /**
     * @param array<string,mixed> $node
     */
    private function writeNode(Worksheet $sheet, int $row, array $node): void
    {
        $type = (string) ($node['type'] ?? '');
        $label = trim((string) ($node['kode'] ?? '') . ' - ' . (string) ($node['nama'] ?? ''), ' -');

        if ($type !== 'rekening') {
            $sheet->mergeCells('A' . $row . ':F' . $row);
            $sheet->setCellValue('A' . $row, $label);
            $sheet->getStyle('A' . $row . ':F' . $row)->applyFromArray([
                'font' => ['bold' => true, 'size' => 9],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F2F2F2']],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '999999']]],
            ]);
            $sheet->getRowDimension($row)->setRowHeight(18);

            return;
        }

        $sheet->setCellValue('A' . $row, (string) ($node['kode'] ?? ''));
        $sheet->setCellValue('B' . $row, (string) ($node['nama'] ?? ''));
        $sheet->setCellValue('C' . $row, (float) ($node['pagu'] ?? 0));
        $sheet->setCellValue('D' . $row, (float) ($node['periode'] ?? 0));
        $sheet->setCellValue('E' . $row, (float) ($node['akumulasi'] ?? 0));
        $sheet->setCellValue('F' . $row, (float) ($node['sisa'] ?? 0));

        $sheet->getStyle('A' . $row . ':F' . $row)->applyFromArray([
            'font' => ['size' => 9],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FDE9D9']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '999999']]],
        ]);

        foreach (['C', 'D', 'E', 'F'] as $col) {
            $sheet->getStyle($col . $row)
                ->getNumberFormat()
                ->setFormatCode(self::NUMBER_FORMAT);
            $sheet->getStyle($col . $row)
                ->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }
        $sheet->getRowDimension($row)->setRowHeight(18);
    }

    /**
     * @param array<string,float|int> $totals
     */
    private function writeTotalRow(Worksheet $sheet, int $row, array $totals): void
    {
        $sheet->mergeCells('A' . $row . ':B' . $row);
        $sheet->setCellValue('A' . $row, 'TOTAL');
        $sheet->setCellValue('C' . $row, (float) ($totals['pagu'] ?? 0));
        $sheet->setCellValue('D' . $row, (float) ($totals['periode'] ?? 0));
        $sheet->setCellValue('E' . $row, (float) ($totals['akumulasi'] ?? 0));
        $sheet->setCellValue('F' . $row, (float) ($totals['sisa'] ?? 0));

        $sheet->getStyle('A' . $row . ':F' . $row)->applyFromArray([
            'font' => ['bold' => true, 'size' => 10],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D9E1F2']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_DOUBLE, 'color' => ['rgb' => '000000']]],
        ]);
        $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        foreach (['C', 'D', 'E', 'F'] as $col) {
            $sheet->getStyle($col . $row)
                ->getNumberFormat()
                ->setFormatCode(self::NUMBER_FORMAT);
            $sheet->getStyle($col . $row)
                ->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }
        $sheet->getRowDimension($row)->setRowHeight(22);
    }

    /**
     * @param array<string,mixed> $report
     */
    private function filename(array $report): string
    {
        $meta = $report['meta'] ?? [];
        if (!empty($meta['nomor_pengajuan'])) {
            $slug = (string) $meta['nomor_pengajuan'];
        } else {
            $slug = 'Periode_' . ($meta['dari'] ?? 'Awal') . '_' . ($meta['sampai'] ?? 'Akhir');
        }
        $slug = preg_replace('/[^A-Za-z0-9]+/', '_', $slug) ?? 'Laporan';
        $slug = trim($slug, '_');

        return 'LPJ_Limpahan_UP_' . $slug . '.xlsx';
    }
}
