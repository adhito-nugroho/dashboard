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
 * Rekap nilai setor (STS) dana TU per sub kegiatan.
 * Sumber: PengajuanTu::getRekapSisaPerSubKegiatan() — hanya TU berstatus cair.
 */
class TuStsExportService
{
    private const NAVY = '1E3A5F';
    private const ZEBRA = 'F8FAFC';
    private const ORG_NAME = 'CABANG DINAS KEHUTANAN (CDK) WILAYAH BOJONEGORO';
    private const NUMBER_FORMAT = '#,##0';
    private const HEADER_ROW = 6;
    private const LAST_COL = 'H';
    private const BULAN = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];

    /**
     * @param array{rows: array<int, array<string, mixed>>, totals: array<string, float|int>} $data
     */
    public function download(array $data): void
    {
        $rows   = $data['rows'];
        $totals = $data['totals'];

        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setCreator('Dashboard Anggaran CDK Wilayah Bojonegoro')
            ->setTitle('Rekap Nilai Setor STS Dana TU')
            ->setSubject('Rekap Nilai Setor (STS) per Sub Kegiatan');

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Rekap STS TU');

        $this->writeTitleBlock($sheet);
        $this->writeHeader($sheet);

        $row = self::HEADER_ROW + 1;
        $no  = 0;
        foreach ($rows as $i => $r) {
            $no++;
            $sheet->setCellValue('A' . $row, $no);
            $sheet->setCellValue('B' . $row, (string) $r['kode_sub_kegiatan']);
            $sheet->setCellValue('C' . $row, (string) $r['nama_sub_kegiatan']);
            $sheet->setCellValue('D' . $row, (string) $r['nama_seksi']);
            $sheet->setCellValue('E' . $row, (int) $r['jumlah_tu']);
            $sheet->setCellValue('F' . $row, (float) $r['total_cair']);
            $sheet->setCellValue('G' . $row, (float) $r['total_terpakai']);
            $sheet->setCellValue('H' . $row, (float) $r['nilai_setor']);

            $sheet->getStyle('A' . $row . ':' . self::LAST_COL . $row)->applyFromArray([
                'font' => ['size' => 9, 'color' => ['rgb' => '1F2937']],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E5E7EB']]],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            ]);
            if ($no % 2 === 0) {
                $sheet->getStyle('A' . $row . ':' . self::LAST_COL . $row)->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB(self::ZEBRA);
            }
            $sheet->getStyle('C' . $row)->getAlignment()->setWrapText(true);
            $sheet->getStyle('D' . $row)->getAlignment()->setWrapText(true);
            foreach (['A', 'E'] as $col) {
                $sheet->getStyle($col . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }
            foreach (['F', 'G', 'H'] as $col) {
                $sheet->getStyle($col . $row)->getNumberFormat()->setFormatCode(self::NUMBER_FORMAT);
                $sheet->getStyle($col . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            }
            $sheet->getStyle('H' . $row)->getFont()->setBold(true);
            $sheet->getRowDimension($row)->setRowHeight(18);
            $row++;
        }

        $sheet->setCellValue('A' . $row, 'TOTAL');
        $sheet->mergeCells('A' . $row . ':D' . $row);
        $sheet->setCellValue('E' . $row, (int) $totals['jumlah_tu']);
        $sheet->setCellValue('F' . $row, (float) $totals['total_cair']);
        $sheet->setCellValue('G' . $row, (float) $totals['total_terpakai']);
        $sheet->setCellValue('H' . $row, (float) $totals['nilai_setor']);
        $sheet->getStyle('A' . $row . ':' . self::LAST_COL . $row)->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::NAVY]],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => self::NAVY]]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getStyle('E' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        foreach (['F', 'G', 'H'] as $col) {
            $sheet->getStyle($col . $row)->getNumberFormat()->setFormatCode(self::NUMBER_FORMAT);
            $sheet->getStyle($col . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }
        $sheet->getRowDimension($row)->setRowHeight(22);

        $row++;
        $sheet->mergeCells('A' . $row . ':' . self::LAST_COL . $row);
        $sheet->setCellValue('A' . $row, 'Nilai setor = dana TU cair − belanja terpakai − setoran yang sudah tercatat, untuk seluruh TU berstatus cair (belum diselesaikan).');
        $sheet->getStyle('A' . $row)->getFont()->setItalic(true)->setSize(8)->getColor()->setRGB('6B7280');

        $sheet->getColumnDimension('A')->setWidth(6);
        $sheet->getColumnDimension('B')->setWidth(18);
        $sheet->getColumnDimension('C')->setWidth(42);
        $sheet->getColumnDimension('D')->setWidth(22);
        $sheet->getColumnDimension('E')->setWidth(9);
        foreach (['F', 'G', 'H'] as $col) {
            $sheet->getColumnDimension($col)->setWidth(18);
        }
        $sheet->freezePane('A' . (self::HEADER_ROW + 1));

        $sheet->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_PORTRAIT)
            ->setPaperSize(PageSetup::PAPERSIZE_A4)
            ->setFitToWidth(1)
            ->setFitToHeight(0);
        $sheet->getPageMargins()->setTop(0.6)->setBottom(0.6)->setLeft(0.5)->setRight(0.5);

        $filename = 'Rekap_STS_TU_Per_Sub_Kegiatan_' . date('Ymd_His') . '.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        header('Pragma: public');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    private function writeTitleBlock(Worksheet $sheet): void
    {
        $sheet->mergeCells('A1:' . self::LAST_COL . '1');
        $sheet->setCellValue('A1', self::ORG_NAME);
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => self::NAVY]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $sheet->mergeCells('A2:' . self::LAST_COL . '2');
        $sheet->setCellValue('A2', 'REKAP NILAI SETOR (STS) DANA TAMBAH UANG (TU)');
        $sheet->getStyle('A2')->applyFromArray([
            'font' => ['bold' => true, 'size' => 13, 'color' => ['rgb' => '111827']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $sheet->mergeCells('A3:' . self::LAST_COL . '3');
        $sheet->setCellValue('A3', 'Per Sub Kegiatan — TU berstatus cair (belum diselesaikan)');
        $sheet->getStyle('A3')->applyFromArray([
            'font' => ['size' => 9, 'color' => ['rgb' => '4B5563']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $now = new \DateTimeImmutable();
        $sheet->mergeCells('A4:' . self::LAST_COL . '4');
        $sheet->setCellValue('A4', 'Dicetak: ' . $now->format('d') . ' ' . self::BULAN[(int) $now->format('n')]
            . ' ' . $now->format('Y'));
        $sheet->getStyle('A4')->applyFromArray([
            'font' => ['size' => 8, 'color' => ['rgb' => '6B7280']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
        ]);

        $sheet->getRowDimension(1)->setRowHeight(20);
        $sheet->getRowDimension(2)->setRowHeight(22);
    }

    private function writeHeader(Worksheet $sheet): void
    {
        $headers = [
            'A' => 'NO',
            'B' => 'KODE SUB KEGIATAN',
            'C' => 'NAMA SUB KEGIATAN',
            'D' => 'SEKSI',
            'E' => 'JML TU',
            'F' => 'DANA CAIR (Rp)',
            'G' => 'TERPAKAI (Rp)',
            'H' => 'NILAI SETOR / STS (Rp)',
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
}
