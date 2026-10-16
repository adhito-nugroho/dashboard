<?php

declare(strict_types=1);

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Service export Excel "Transfer Perjalanan Dinas" — berbasis transaksi.
 *
 * Input: daftar baris transaksi perjalanan dinas yang sudah dikonfigurasi
 * (uraian, nama, nilai, transfer_ke, potongan).
 *
 * Output satu file berisi:
 *   - Sheet "Detail" : rincian per transaksi (sesuai data mentah yang dipivot).
 *                      Kolom: uraian | nama | penerimaan | transfer ke |
 *                             potongan | penerimaan bersih.
 *   - Sheet "Rekap"  : pivot ringkas, satu baris per Transfer Ke.
 *                      Kolom: Transfer Ke | Jumlah Transaksi | Penerimaan |
 *                             Potongan | Penerimaan Bersih + Grand Total.
 *
 * Penerimaan (gross) = transaksi.nilai. Penerimaan bersih = nilai - potongan.
 */
class TransferPerjadinExportService
{
    private const HEADER_FILL = '1B4332';
    private const TOTAL_FILL  = 'B7E4C7';
    private const CURRENCY    = '#,##0';

    public static function normNama(?string $s): string
    {
        $s = strtoupper(trim((string) $s));
        $s = preg_replace('/\s+/', ' ', $s) ?? $s;
        return trim($s);
    }

    /**
     * @param array<int, array{uraian?:string, nama?:string, nilai?:float, transfer_ke?:string, potongan?:float}> $rows
     */
    public function buildSpreadsheet(array $rows): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $this->buildRekapSheet($spreadsheet->getActiveSheet(), $rows);
        $this->buildDetailSheet($spreadsheet->createSheet(), $rows);
        $spreadsheet->setActiveSheetIndex(0);
        return $spreadsheet;
    }

    /**
     * Pivot: kelompokkan baris per Transfer Ke.
     *
     * @return array<int, array{label:string, count:int, penerimaan:float, potongan:float, bersih:float}>
     */
    public function pivotByTransferKe(array $rows): array
    {
        $groups = [];
        foreach ($rows as $r) {
            $label = trim((string) ($r['transfer_ke'] ?? ''));
            if ($label === '') {
                $label = '(Belum ditentukan)';
            }
            $key = self::normNama($label);
            if (!isset($groups[$key])) {
                $groups[$key] = ['label' => $label, 'count' => 0, 'penerimaan' => 0.0, 'potongan' => 0.0, 'bersih' => 0.0];
            }
            $nilai = (float) ($r['nilai'] ?? 0);
            $potongan = (float) ($r['potongan'] ?? 0);
            $groups[$key]['count']++;
            $groups[$key]['penerimaan'] += $nilai;
            $groups[$key]['potongan'] += $potongan;
            $groups[$key]['bersih'] += ($nilai - $potongan);
        }

        uksort($groups, function (string $a, string $b): int {
            $aEmpty = $a === self::normNama('(Belum ditentukan)');
            $bEmpty = $b === self::normNama('(Belum ditentukan)');
            if ($aEmpty !== $bEmpty) {
                return $aEmpty ? 1 : -1;
            }
            return strcmp($a, $b);
        });

        return array_values($groups);
    }

    private function buildRekapSheet(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, array $rows): void
    {
        $sheet->setTitle('Rekap');

        $sheet->mergeCells('A1:E1');
        $sheet->setCellValue('A1', 'REKAP TRANSFER PERJALANAN DINAS');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle('A1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::HEADER_FILL);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getRowDimension(1)->setRowHeight(24);

        $sheet->mergeCells('A2:E2');
        $sheet->setCellValue('A2', 'Cabang Dinas Kehutanan Wilayah Bojonegoro');
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(11);
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->mergeCells('A3:E3');
        $sheet->setCellValue('A3', 'Dicetak: ' . date('d/m/Y H:i') . ' WIB');
        $sheet->getStyle('A3')->getFont()->setSize(9)->getColor()->setRGB('64748B');
        $sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $headerRow = 5;
        $this->writeHeader($sheet, $headerRow, ['Transfer Ke', 'Jumlah Transaksi', 'Penerimaan', 'Potongan', 'Penerimaan Bersih']);

        $row = $headerRow + 1;
        $grandCount = 0;
        $grandPenerimaan = 0.0;
        $grandPotongan = 0.0;
        $grandBersih = 0.0;

        foreach ($this->pivotByTransferKe($rows) as $g) {
            $sheet->setCellValue('A' . $row, $g['label']);
            $sheet->setCellValue('B' . $row, $g['count']);
            $sheet->setCellValue('C' . $row, $g['penerimaan']);
            $sheet->setCellValue('D' . $row, $g['potongan']);
            $sheet->setCellValue('E' . $row, $g['bersih']);
            $this->applyCurrency($sheet, 'C' . $row);
            $this->applyCurrency($sheet, 'D' . $row);
            $this->applyCurrency($sheet, 'E' . $row);
            $sheet->getStyle('B' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $this->applyThinBorders($sheet, 'A' . $row . ':E' . $row);

            $grandCount += $g['count'];
            $grandPenerimaan += $g['penerimaan'];
            $grandPotongan += $g['potongan'];
            $grandBersih += $g['bersih'];
            $row++;
        }

        if ($rows === []) {
            $sheet->mergeCells('A' . $row . ':E' . $row);
            $sheet->setCellValue('A' . $row, 'Belum ada transaksi perjalanan dinas yang dikonfigurasi transfernya.');
            $sheet->getStyle('A' . $row)->getFont()->setItalic(true)->getColor()->setRGB('64748B');
            $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        } else {
            $sheet->setCellValue('A' . $row, 'GRAND TOTAL');
            $sheet->setCellValue('B' . $row, $grandCount);
            $sheet->setCellValue('C' . $row, $grandPenerimaan);
            $sheet->setCellValue('D' . $row, $grandPotongan);
            $sheet->setCellValue('E' . $row, $grandBersih);
            $this->applyCurrency($sheet, 'C' . $row);
            $this->applyCurrency($sheet, 'D' . $row);
            $this->applyCurrency($sheet, 'E' . $row);
            $sheet->getStyle('B' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('A' . $row . ':E' . $row)->getFont()->setBold(true);
            $sheet->getStyle('A' . $row . ':E' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::TOTAL_FILL);
            $this->applyThinBorders($sheet, 'A' . $row . ':E' . $row);
        }

        foreach (['A' => 34, 'B' => 18, 'C' => 18, 'D' => 15, 'E' => 20] as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }
        $sheet->freezePane('A' . ($headerRow + 1));
    }

    private function buildDetailSheet(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, array $rows): void
    {
        $sheet->setTitle('Detail');

        $sheet->mergeCells('A1:F1');
        $sheet->setCellValue('A1', 'DETAIL TRANSFER PERJALANAN DINAS');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle('A1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::HEADER_FILL);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getRowDimension(1)->setRowHeight(24);

        $sheet->mergeCells('A2:F2');
        $sheet->setCellValue('A2', 'Rincian transaksi perjalanan dinas penyusun angka rekap');
        $sheet->getStyle('A2')->getFont()->setSize(10)->getColor()->setRGB('64748B');
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $headerRow = 4;
        $this->writeHeader($sheet, $headerRow, ['Uraian', 'Nama', 'Penerimaan', 'Transfer Ke', 'Potongan', 'Penerimaan Bersih']);

        $row = $headerRow + 1;
        foreach ($rows as $r) {
            $nilai = (float) ($r['nilai'] ?? 0);
            $potongan = (float) ($r['potongan'] ?? 0);
            $ke = trim((string) ($r['transfer_ke'] ?? ''));
            $sheet->setCellValue('A' . $row, (string) ($r['uraian'] ?? ''));
            $sheet->setCellValue('B' . $row, (string) ($r['nama'] ?? ''));
            $sheet->setCellValue('C' . $row, $nilai);
            $sheet->setCellValue('D' . $row, $ke !== '' ? $ke : '-');
            $sheet->setCellValue('E' . $row, $potongan);
            $sheet->setCellValue('F' . $row, $nilai - $potongan);
            $this->applyCurrency($sheet, 'C' . $row);
            $this->applyCurrency($sheet, 'E' . $row);
            $this->applyCurrency($sheet, 'F' . $row);
            $sheet->getStyle('A' . $row)->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
            $this->applyThinBorders($sheet, 'A' . $row . ':F' . $row);
            $row++;
        }

        if ($rows === []) {
            $sheet->mergeCells('A' . $row . ':F' . $row);
            $sheet->setCellValue('A' . $row, 'Belum ada data.');
            $sheet->getStyle('A' . $row)->getFont()->setItalic(true)->getColor()->setRGB('64748B');
            $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        foreach (['A' => 55, 'B' => 28, 'C' => 16, 'D' => 30, 'E' => 14, 'F' => 18] as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }
        $sheet->freezePane('A' . ($headerRow + 1));
    }

    private function writeHeader(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, int $headerRow, array $headers): void
    {
        $col = 'A';
        foreach ($headers as $h) {
            $sheet->setCellValue($col . $headerRow, $h);
            $col++;
        }
        $lastCol = chr(ord('A') + count($headers) - 1);
        $sheet->getStyle('A' . $headerRow . ':' . $lastCol . $headerRow)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle('A' . $headerRow . ':' . $lastCol . $headerRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::HEADER_FILL);
        $sheet->getStyle('A' . $headerRow . ':' . $lastCol . $headerRow)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getRowDimension($headerRow)->setRowHeight(20);
        $this->applyThinBorders($sheet, 'A' . $headerRow . ':' . $lastCol . $headerRow);
    }

    private function applyCurrency(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, string $cell): void
    {
        $sheet->getStyle($cell)->getNumberFormat()->setFormatCode(self::CURRENCY);
        $sheet->getStyle($cell)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    }

    private function applyThinBorders(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('D1D5DB');
    }

    /**
     * @param array<int, array> $rows
     */
    public function download(array $rows, ?string $filename = null): void
    {
        $filename = $filename ?: ('Transfer_Perjalanan_Dinas_' . date('Ymd_His') . '.xlsx');

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        header('Pragma: public');

        $writer = new Xlsx($this->buildSpreadsheet($rows));
        $writer->save('php://output');
        exit;
    }
}
