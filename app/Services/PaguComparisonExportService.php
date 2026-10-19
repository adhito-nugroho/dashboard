<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Pagu;
use App\Models\PaguRiwayat;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Ringkasan Excel perbandingan APBD murni (sebelum) dan PAPBD (sesudah).
 *
 * Hierarki mengikuti laporan realisasi:
 * Program -> Kegiatan -> Sub Kegiatan -> Rekening.
 */
class PaguComparisonExportService
{
    private const LAST_COL = 'F';
    private const HEADER_ROW = 4;
    private const NUMBER_FORMAT = '#,##0';
    private const DIFFERENCE_FORMAT = '#,##0;[Red]-#,##0';

    public function __construct(
        private Pagu $paguModel,
        private ?PaguRiwayat $riwayatModel = null
    ) {
    }

    public function download(int $tahun): void
    {
        $report = $this->buildReport($tahun);
        $spreadsheet = $this->createSpreadsheet($report);

        $filename = 'Ringkasan_APBD_vs_PAPBD_' . $tahun . '.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        header('Pragma: public');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    /**
     * @return array{
     *     tahun:int,
     *     rows:array<int,array<string,mixed>>,
     *     totals:array{before:float,after:float,difference:float},
     *     counts:array{rekening:int,berubah:int,bertambah:int,berkurang:int}
     * }
     */
    public function buildReport(int $tahun): array
    {
        $pagus = array_values(array_filter(
            $this->paguModel->getAll(),
            fn(array $pagu): bool => (int) ($pagu['tahun'] ?? 0) === $tahun
        ));

        $nilaiAwalMap = $this->riwayatModel !== null
            ? $this->riwayatModel->getPaguAwalMapByTahun($tahun, $pagus)
            : [];

        $hierarchy = [];

        foreach ($pagus as $pagu) {
            $paguId = (int) ($pagu['id'] ?? 0);
            $before = array_key_exists($paguId, $nilaiAwalMap)
                ? (float) $nilaiAwalMap[$paguId]
                : (float) ($pagu['nilai_pagu'] ?? 0);
            $after = (float) ($pagu['nilai_pagu'] ?? 0);

            $programId = (string) ($pagu['program_id'] ?? '0');
            $kegiatanId = (string) ($pagu['kegiatan_id'] ?? '0');
            $subKegiatanId = (string) ($pagu['sub_kegiatan_id'] ?? '0');
            $rekeningId = (string) ($pagu['rekening_id'] ?? '0');

            if (!isset($hierarchy[$programId])) {
                $hierarchy[$programId] = $this->node(
                    'program',
                    (string) ($pagu['kode_program'] ?? ''),
                    (string) ($pagu['nama_program'] ?? '')
                );
            }
            $this->accumulate($hierarchy[$programId], $before, $after);

            if (!isset($hierarchy[$programId]['children'][$kegiatanId])) {
                $hierarchy[$programId]['children'][$kegiatanId] = $this->node(
                    'kegiatan',
                    (string) ($pagu['kode_kegiatan'] ?? ''),
                    (string) ($pagu['nama_kegiatan'] ?? '')
                );
            }
            $this->accumulate($hierarchy[$programId]['children'][$kegiatanId], $before, $after);

            if (!isset($hierarchy[$programId]['children'][$kegiatanId]['children'][$subKegiatanId])) {
                $hierarchy[$programId]['children'][$kegiatanId]['children'][$subKegiatanId] = $this->node(
                    'sub_kegiatan',
                    (string) ($pagu['kode_sub_kegiatan'] ?? ''),
                    (string) ($pagu['nama_sub_kegiatan'] ?? '')
                );
            }
            $this->accumulate(
                $hierarchy[$programId]['children'][$kegiatanId]['children'][$subKegiatanId],
                $before,
                $after
            );

            if (!isset($hierarchy[$programId]['children'][$kegiatanId]['children'][$subKegiatanId]['children'][$rekeningId])) {
                $hierarchy[$programId]['children'][$kegiatanId]['children'][$subKegiatanId]['children'][$rekeningId] = $this->node(
                    'rekening',
                    (string) ($pagu['kode_rekening'] ?? ''),
                    (string) ($pagu['nama_rekening'] ?? '')
                );
            }
            $this->accumulate(
                $hierarchy[$programId]['children'][$kegiatanId]['children'][$subKegiatanId]['children'][$rekeningId],
                $before,
                $after
            );
        }

        $this->sortTree($hierarchy);

        $rows = [];
        $this->flattenTree($hierarchy, $rows);

        $totals = ['before' => 0.0, 'after' => 0.0, 'difference' => 0.0];
        $counts = ['rekening' => 0, 'berubah' => 0, 'bertambah' => 0, 'berkurang' => 0];

        foreach ($hierarchy as $program) {
            $totals['before'] += (float) $program['before'];
            $totals['after'] += (float) $program['after'];
        }
        $totals['difference'] = $totals['after'] - $totals['before'];

        foreach ($rows as $row) {
            if (($row['type'] ?? '') !== 'rekening') {
                continue;
            }
            $counts['rekening']++;
            if ((float) $row['difference'] > 0.009) {
                $counts['berubah']++;
                $counts['bertambah']++;
            } elseif ((float) $row['difference'] < -0.009) {
                $counts['berubah']++;
                $counts['berkurang']++;
            }
        }

        return [
            'tahun' => $tahun,
            'rows' => $rows,
            'totals' => $totals,
            'counts' => $counts,
        ];
    }

    /**
     * @param array<string,mixed> $report
     */
    public function createSpreadsheet(array $report): Spreadsheet
    {
        $tahun = (int) ($report['tahun'] ?? date('Y'));
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('APBD vs PAPBD');

        $this->writeTitle($sheet, $tahun);
        $this->writeTableHeader($sheet, self::HEADER_ROW);

        $row = self::HEADER_ROW + 1;
        $this->writeSummaryRow(
            $sheet,
            $row,
            $report['totals'] ?? [],
            $report['counts'] ?? [],
            'TOTAL KESELURUHAN',
            'CABANG DINAS KEHUTANAN (CDK) WIL. BOJONEGORO'
        );
        $row++;

        foreach ($report['rows'] ?? [] as $node) {
            $this->writeNode($sheet, $row, $node);
            $row++;
        }

        if ($row === self::HEADER_ROW + 2) {
            $sheet->mergeCells('A' . $row . ':' . self::LAST_COL . $row);
            $sheet->setCellValue('A' . $row, 'Belum ada data pagu untuk tahun ' . $tahun . '.');
            $sheet->getStyle('A' . $row . ':' . self::LAST_COL . $row)->applyFromArray([
                'font' => ['italic' => true, 'color' => ['rgb' => '666666']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CCCCCC']]],
            ]);
            $row++;
        }

        $this->writeTotalRow(
            $sheet,
            $row,
            $report['totals'] ?? [],
            $report['counts'] ?? []
        );

        $sheet->getColumnDimension('A')->setWidth(18);
        $sheet->getColumnDimension('B')->setWidth(62);
        foreach (['C', 'D', 'E'] as $col) {
            $sheet->getColumnDimension($col)->setWidth(20);
        }
        $sheet->getColumnDimension('F')->setWidth(30);

        $sheet->freezePane('A' . (self::HEADER_ROW + 1));
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

        return $spreadsheet;
    }

    private function writeTitle(Worksheet $sheet, int $tahun): void
    {
        $sheet->mergeCells('A1:' . self::LAST_COL . '1');
        $sheet->setCellValue('A1', 'RINGKASAN PERBANDINGAN APBD MURNI DAN PAPBD TAHUN ANGGARAN ' . $tahun);
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '1E3A5F']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(30);

        $sheet->mergeCells('A2:' . self::LAST_COL . '2');
        $sheet->setCellValue('A2', 'CDK WILAYAH BOJONEGORO');
        $sheet->getStyle('A2')->applyFromArray([
            'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => '374151']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(2)->setRowHeight(22);
        $sheet->getRowDimension(3)->setRowHeight(8);
    }

    private function writeTableHeader(Worksheet $sheet, int $row): void
    {
        $headers = [
            'A' => 'KODE',
            'B' => 'PROGRAM / KEGIATAN / SUB KEGIATAN / REKENING',
            'C' => "APBD MURNI\n(SEBELUM)",
            'D' => "PAPBD / PERUBAHAN\n(SESUDAH)",
            'E' => 'SELISIH',
            'F' => 'KETERANGAN',
        ];

        foreach ($headers as $col => $label) {
            $sheet->setCellValue($col . $row, $label);
        }

        $sheet->getStyle('A' . $row . ':' . self::LAST_COL . $row)->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A5F']],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'FFFFFF']]],
        ]);
        $sheet->getRowDimension($row)->setRowHeight(30);
    }

    /**
     * @param array<string,mixed> $totals
     * @param array<string,mixed> $counts
     */
    private function writeSummaryRow(
        Worksheet $sheet,
        int $row,
        array $totals,
        array $counts,
        string $label,
        string $description
    ): void {
        $sheet->setCellValue('A' . $row, $label);
        $sheet->setCellValue('B' . $row, $description);
        $sheet->setCellValue('C' . $row, (float) ($totals['before'] ?? 0));
        $sheet->setCellValue('D' . $row, (float) ($totals['after'] ?? 0));
        $sheet->setCellValue('E' . $row, (float) ($totals['difference'] ?? 0));
        $sheet->setCellValue('F' . $row, $this->changeSummary($counts));

        $sheet->getStyle('A' . $row . ':' . self::LAST_COL . $row)->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => '000000']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFF00']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'AAAAAA']]],
        ]);
        $sheet->getRowDimension($row)->setRowHeight(20);
        $this->applyNumberFormat($sheet, $row, false);
    }

    /**
     * @param array<string,mixed> $node
     */
    private function writeNode(Worksheet $sheet, int $row, array $node): void
    {
        $type = (string) ($node['type'] ?? 'rekening');
        $difference = (float) ($node['difference'] ?? 0);

        $indent = match ($type) {
            'kegiatan' => '  ',
            'sub_kegiatan' => '    ',
            'rekening' => '      ',
            default => '',
        };

        $sheet->setCellValue('A' . $row, (string) ($node['kode'] ?? ''));
        $sheet->setCellValue('B' . $row, $indent . (string) ($node['nama'] ?? ''));
        $sheet->setCellValue('C' . $row, (float) ($node['before'] ?? 0));
        $sheet->setCellValue('D' . $row, (float) ($node['after'] ?? 0));
        $sheet->setCellValue('E' . $row, $difference);

        $keterangan = '';
        $fontColor = '000000';
        if ($type === 'rekening') {
            if ($difference > 0.009) {
                $keterangan = 'Bertambah';
                $fontColor = '15803D';
            } elseif ($difference < -0.009) {
                $keterangan = 'Berkurang';
                $fontColor = 'B91C1C';
            } else {
                $keterangan = 'Tetap';
                $fontColor = '6B7280';
            }
        }
        $sheet->setCellValue('F' . $row, $keterangan);

        $bgColor = match ($type) {
            'program' => 'D6E4F0',
            'kegiatan' => 'E8F5E9',
            'sub_kegiatan' => 'FFF9E6',
            default => 'FFFFFF',
        };

        $bold = in_array($type, ['program', 'kegiatan'], true);
        $fontSize = match ($type) {
            'program' => 10,
            'kegiatan' => 9,
            default => 9,
        };

        $sheet->getStyle('A' . $row . ':' . self::LAST_COL . $row)->applyFromArray([
            'font' => ['bold' => $bold, 'size' => $fontSize, 'color' => ['rgb' => '000000']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bgColor]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CCCCCC']]],
        ]);
        if ($keterangan !== '') {
            $sheet->getStyle('F' . $row)->getFont()->getColor()->setRGB($fontColor);
        }
        $sheet->getRowDimension($row)->setRowHeight(18);
        $this->applyNumberFormat($sheet, $row, true);
    }

    /**
     * @param array<string,mixed> $totals
     * @param array<string,mixed> $counts
     */
    private function writeTotalRow(Worksheet $sheet, int $row, array $totals, array $counts): void
    {
        $sheet->mergeCells('A' . $row . ':B' . $row);
        $sheet->setCellValue('A' . $row, 'JUMLAH TOTAL');
        $sheet->setCellValue('C' . $row, (float) ($totals['before'] ?? 0));
        $sheet->setCellValue('D' . $row, (float) ($totals['after'] ?? 0));
        $sheet->setCellValue('E' . $row, (float) ($totals['difference'] ?? 0));
        $sheet->setCellValue('F' . $row, $this->changeSummary($counts));

        $sheet->getStyle('A' . $row . ':' . self::LAST_COL . $row)->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A5F']],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER,
                'horizontal' => Alignment::HORIZONTAL_LEFT,
            ],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => 'FFFFFF']]],
        ]);
        $sheet->getRowDimension($row)->setRowHeight(22);
        $this->applyNumberFormat($sheet, $row, false);
    }

    private function applyNumberFormat(Worksheet $sheet, int $row, bool $withDifference): void
    {
        foreach (['C', 'D'] as $col) {
            $sheet->getStyle($col . $row)->getNumberFormat()->setFormatCode(self::NUMBER_FORMAT);
            $sheet->getStyle($col . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }

        $differenceFormat = $withDifference ? self::DIFFERENCE_FORMAT : self::NUMBER_FORMAT;
        $sheet->getStyle('E' . $row)->getNumberFormat()->setFormatCode($differenceFormat);
        $sheet->getStyle('E' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    }

    /**
     * @param array<string,mixed> $counts
     */
    private function changeSummary(array $counts): string
    {
        $changed = (int) ($counts['berubah'] ?? 0);
        $increased = (int) ($counts['bertambah'] ?? 0);
        $decreased = (int) ($counts['berkurang'] ?? 0);

        if ($changed === 0) {
            return 'Tidak ada perubahan';
        }

        return $changed . ' rekening berubah (' . $increased . ' naik / ' . $decreased . ' turun)';
    }

    /**
     * @return array<string,mixed>
     */
    private function node(string $type, string $kode, string $nama): array
    {
        return [
            'type' => $type,
            'kode' => $kode,
            'nama' => $nama,
            'before' => 0.0,
            'after' => 0.0,
            'difference' => 0.0,
            'children' => [],
        ];
    }

    /**
     * @param array<string,mixed> $node
     */
    private function accumulate(array &$node, float $before, float $after): void
    {
        $node['before'] = (float) $node['before'] + $before;
        $node['after'] = (float) $node['after'] + $after;
        $node['difference'] = (float) $node['after'] - (float) $node['before'];
    }

    /**
     * @param array<string,array<string,mixed>> $nodes
     */
    private function sortTree(array &$nodes): void
    {
        uasort($nodes, fn(array $a, array $b): int => strcmp((string) $a['kode'], (string) $b['kode']));

        foreach ($nodes as &$node) {
            if (!empty($node['children'])) {
                $this->sortTree($node['children']);
            }
        }
        unset($node);
    }

    /**
     * @param array<string,array<string,mixed>> $nodes
     * @param array<int,array<string,mixed>> $rows
     */
    private function flattenTree(array $nodes, array &$rows): void
    {
        foreach ($nodes as $node) {
            $children = $node['children'] ?? [];
            unset($node['children']);
            $rows[] = $node;

            if (!empty($children)) {
                $this->flattenTree($children, $rows);
            }
        }
    }
}
