<?php

namespace App\Controllers;

use App\Models\Pagu;
use App\Models\Rak;
use App\Models\Transaksi;
use App\Models\Seksi;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;

class ExcelController {
    private Pagu $paguModel;
    private Rak $rakModel;
    private Transaksi $transaksiModel;
    private Seksi $seksiModel;

    public function __construct(
        Pagu $paguModel,
        Rak $rakModel,
        Transaksi $transaksiModel,
        Seksi $seksiModel
    ) {
        $this->paguModel = $paguModel;
        $this->rakModel = $rakModel;
        $this->transaksiModel = $transaksiModel;
        $this->seksiModel = $seksiModel;
    }

    /**
     * Export Laporan Realisasi Anggaran to Excel
     */
    public function exportLaporan(): void {
        $tahun = isset($_GET['tahun']) ? (int) $_GET['tahun'] : (int) date('Y');
        $bulan = isset($_GET['bulan']) ? (int) $_GET['bulan'] : (int) date('n');
        $bulan = max(1, min(12, $bulan));

        $bulanNamesLong = [1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',
                           7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'];
        $namaBulan = $bulanNamesLong[$bulan];

        // -------------------------------------------------------
        // 1. Build hierarchical data: Seksi -> Program -> Kegiatan
        //    -> Sub Kegiatan -> Rekening
        // -------------------------------------------------------
        $seksis = $this->seksiModel->getAll();
        $seksiMap = [];
        $seksiKodeMap = [];
        foreach ($seksis as $s) {
            $seksiMap[$s['id']] = $s['nama_seksi'];
            $seksiKodeMap[$s['id']] = $s['kode_seksi'] ?? '';
        }

        $pagus = $this->paguModel->getAll();
        $hierarchy = [];

        foreach ($pagus as $pagu) {
            if ($pagu['tahun'] != $tahun) continue;

            $sid  = $pagu['sub_kegiatan_seksi_id'] ?? 0;
            $pid  = $pagu['program_id'];
            $kid  = $pagu['kegiatan_id'];
            $skid = $pagu['sub_kegiatan_id'];
            $rid  = $pagu['rekening_id'];

            // RAK for the selected month (cached batch)
            $rakBulan   = $this->getRakByRekeningAndYear($rid, $tahun);
            $monthlyRak = array_fill(1, 12, 0);
            foreach ($rakBulan as $r) {
                $monthlyRak[$r['bulan']] += (float)$r['nilai_rak'];
            }

            // Realisasi — only up to selected bulan (cached batch)
            $transaksis = $this->getTransaksiByRekeningAndYear($rid, $tahun);
            $realisasiSd          = 0;  // s/d bulan terpilih
            $realisasiSebelumnya  = 0;  // s/d bulan terpilih - 1
            $realisasiSaatIni     = 0;  // bulan terpilih saja

            foreach ($transaksis as $t) {
                $tBulan = (int) date('n', strtotime($t['tanggal']));
                if ($tBulan <= $bulan) {
                    $realisasiSd += (float)$t['nilai'];
                    if ($tBulan < $bulan) {
                        $realisasiSebelumnya += (float)$t['nilai'];
                    } else {
                        $realisasiSaatIni += (float)$t['nilai'];
                    }
                }
            }

            $paguRek = (float)$pagu['nilai_pagu'];

            // Rekening row
            $rekRow = [
                'kode'                 => $pagu['kode_rekening'],
                'nama'                 => $pagu['nama_rekening'],
                'pagu'                 => $paguRek,
                'rak_saat_ini'         => $monthlyRak[$bulan] ?? 0,
                'realisasi_saat_ini'   => $realisasiSaatIni,
                'realisasi_sebelumnya' => $realisasiSebelumnya,
                'realisasi_sd'         => $realisasiSd,
                'jumlah_realisasi'     => $realisasiSd,
                'sisa'                 => $paguRek - $realisasiSd,
                'pct_keuangan'         => $paguRek > 0 ? ($realisasiSd / $paguRek) * 100 : 0,
                'children'             => [],
                'type'                 => 'rekening',
            ];

            // Build tree
            if (!isset($hierarchy[$sid])) {
                $hierarchy[$sid] = $this->initNode('seksi', $seksiKodeMap[$sid] ?? '', $seksiMap[$sid] ?? 'Tanpa Seksi');
            }
            if (!isset($hierarchy[$sid]['children'][$pid])) {
                $hierarchy[$sid]['children'][$pid] = $this->initNode('program', $pagu['kode_program'] ?? '', $pagu['nama_program'] ?? '');
            }
            if (!isset($hierarchy[$sid]['children'][$pid]['children'][$kid])) {
                $hierarchy[$sid]['children'][$pid]['children'][$kid] = $this->initNode('kegiatan', $pagu['kode_kegiatan'] ?? '', $pagu['nama_kegiatan'] ?? '');
            }
            if (!isset($hierarchy[$sid]['children'][$pid]['children'][$kid]['children'][$skid])) {
                $hierarchy[$sid]['children'][$pid]['children'][$kid]['children'][$skid] = $this->initNode('sub_kegiatan', $pagu['kode_sub_kegiatan'] ?? '', $pagu['nama_sub_kegiatan'] ?? '');
            }

            $hierarchy[$sid]['children'][$pid]['children'][$kid]['children'][$skid]['children'][$rid] = $rekRow;

            // Accumulate upwards
            $this->accum($hierarchy[$sid]['children'][$pid]['children'][$kid]['children'][$skid], $rekRow);
            $this->accum($hierarchy[$sid]['children'][$pid]['children'][$kid], $rekRow);
            $this->accum($hierarchy[$sid]['children'][$pid], $rekRow);
            $this->accum($hierarchy[$sid], $rekRow);
        }

        // Sort
        uasort($hierarchy, fn($a,$b) => strcmp($a['kode'],$b['kode']));
        foreach ($hierarchy as &$s) {
            uasort($s['children'], fn($a,$b) => strcmp($a['kode'],$b['kode']));
            foreach ($s['children'] as &$p) {
                uasort($p['children'], fn($a,$b) => strcmp($a['kode'],$b['kode']));
                foreach ($p['children'] as &$k) {
                    uasort($k['children'], fn($a,$b) => strcmp($a['kode'],$b['kode']));
                    foreach ($k['children'] as &$sk) {
                        uasort($sk['children'], fn($a,$b) => strcmp($a['kode'],$b['kode']));
                    }
                }
            }
        }
        unset($s, $p, $k, $sk);

        // -------------------------------------------------------
        // 2. Build Spreadsheet
        // -------------------------------------------------------
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Laporan Realisasi');

        // ---- Column widths ----
        $sheet->getColumnDimension('A')->setWidth(55);
        $sheet->getColumnDimension('B')->setWidth(20);
        $sheet->getColumnDimension('C')->setWidth(20);
        $sheet->getColumnDimension('D')->setWidth(20);
        $sheet->getColumnDimension('E')->setWidth(20);
        $sheet->getColumnDimension('F')->setWidth(20);
        $sheet->getColumnDimension('G')->setWidth(14);
        $sheet->getColumnDimension('H')->setWidth(20);

        // ---- Row 1-2: Report Title ----
        $sheet->mergeCells('A1:H1');
        $sheet->setCellValue('A1', 'LAPORAN REALISASI ANGGARAN BULAN ' . strtoupper($namaBulan) . ' ' . $tahun);
        $titleStyle1 = [
            'font'      => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '1E3A5F']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ];
        $sheet->getStyle('A1')->applyFromArray($titleStyle1);
        $sheet->getRowDimension(1)->setRowHeight(30);

        $sheet->mergeCells('A2:H2');
        $sheet->setCellValue('A2', 'CDK WILAYAH BOJONEGORO');
        $titleStyle2 = [
            'font'      => ['bold' => true, 'size' => 12, 'color' => ['rgb' => '374151']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ];
        $sheet->getStyle('A2')->applyFromArray($titleStyle2);
        $sheet->getRowDimension(2)->setRowHeight(22);

        // Row 3: blank spacer
        $sheet->getRowDimension(3)->setRowHeight(8);

        // ---- Row 4-6: Column headers (shifted +3 from original 1-3) ----
        $sheet->mergeCells('A4:A6');
        $sheet->setCellValue('A4', 'PROGRAM/KEGIATAN/SUB KEGIATAN');

        $sheet->mergeCells('B4:B6');
        $sheet->setCellValue('B4', 'ANGGARAN');

        $sheet->mergeCells('C4:E4');
        $sheet->setCellValue('C4', 'REALISASI (UP/GU/TU)');

        $sheet->mergeCells('F4:F6');
        $sheet->setCellValue('F4', 'JUMLAH REALISASI');

        $sheet->mergeCells('G4:G6');
        $sheet->setCellValue('G4', 'PRESENTASE (%)');

        $sheet->mergeCells('H4:H6');
        $sheet->setCellValue('H4', 'SISA ANGGARAN');

        // ---- Row 5 sub-headers ----
        $sheet->setCellValue('C5', 'BULAN ' . strtoupper($namaBulan));
        $sheet->mergeCells('C5:C6');
        $sheet->setCellValue('D5', 'S/D BULAN LALU');
        $sheet->mergeCells('D5:D6');
        $sheet->setCellValue('E5', 'S/D ' . strtoupper($namaBulan) . ' ' . $tahun);
        $sheet->mergeCells('E5:E6');

        $sheet->setCellValue('G5', 'KEUANGAN');
        $sheet->mergeCells('G5:G6');

        // ---- Apply header style (rows 4-6) ----
        $headerStyle = [
            'font'      => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A5F']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'FFFFFF']]],
        ];
        $sheet->getStyle('A4:H6')->applyFromArray($headerStyle);
        $sheet->getRowDimension(4)->setRowHeight(24);
        $sheet->getRowDimension(5)->setRowHeight(24);
        $sheet->getRowDimension(6)->setRowHeight(20);

        // ---- Calculate totals for Grand Total row ----
        $grandTotal = $this->initNode('total', '', 'TOTAL KESELURUHAN');
        foreach ($hierarchy as $secNode) {
            $this->accum($grandTotal, $secNode);
        }

        // ---- Write grand total row (CDK header row) ----
        $currentRow = 7;
        $sheet->setCellValue("A{$currentRow}", 'CABANG DINAS KEHUTANAN (CDK) WIL. BOJONEGORO — s/d ' . strtoupper($namaBulan) . ' ' . $tahun);
        $sheet->setCellValue("B{$currentRow}", $grandTotal['pagu']);
        $sheet->setCellValue("C{$currentRow}", $grandTotal['realisasi_saat_ini']);
        $sheet->setCellValue("D{$currentRow}", $grandTotal['realisasi_sebelumnya']);
        $sheet->setCellValue("E{$currentRow}", $grandTotal['realisasi_sd']);
        $sheet->setCellValue("F{$currentRow}", $grandTotal['jumlah_realisasi']);
        $sheet->setCellValue("G{$currentRow}", $grandTotal['pagu'] > 0 ? ($grandTotal['jumlah_realisasi'] / $grandTotal['pagu']) * 100 : 0);
        $sheet->setCellValue("H{$currentRow}", $grandTotal['sisa']);

        $cdkStyle = [
            'font'      => ['bold' => true, 'size' => 10, 'color' => ['rgb' => '000000']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFF00']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'AAAAAA']]],
        ];
        $sheet->getStyle("A{$currentRow}:H{$currentRow}")->applyFromArray($cdkStyle);
        $sheet->getRowDimension($currentRow)->setRowHeight(20);
        $this->applyNumberFormat($sheet, $currentRow);
        $currentRow++;

        // ---- Write hierarchy rows ---- track seksi row numbers ----
        $seksiRows = []; // Row numbers of seksi-level rows (for correct grand total)

        foreach ($hierarchy as $seksiNode) {
            $seksiRows[] = $currentRow;          // remember this seksi's row
            $this->writeRow($sheet, $currentRow, $seksiNode, 'seksi');
            $currentRow++;

            foreach ($seksiNode['children'] as $progNode) {
                $this->writeRow($sheet, $currentRow, $progNode, 'program');
                $currentRow++;

                foreach ($progNode['children'] as $kegNode) {
                    $this->writeRow($sheet, $currentRow, $kegNode, 'kegiatan');
                    $currentRow++;

                    foreach ($kegNode['children'] as $skNode) {
                        $this->writeRow($sheet, $currentRow, $skNode, 'sub_kegiatan');
                        $currentRow++;

                        foreach ($skNode['children'] as $rekNode) {
                            $this->writeRow($sheet, $currentRow, $rekNode, 'rekening');
                            $currentRow++;
                        }
                    }
                }
            }
        }

        // ---- Grand Total Row (bottom) — sum only seksi rows ----
        $sheet->setCellValue("A{$currentRow}", 'JUMLAH TOTAL');

        // Build SUM referencing only seksi-level rows, e.g. =B5+B20+B40
        foreach (['B','C','D','E','F','H'] as $col) {
            $refs = array_map(fn($r) => "{$col}{$r}", $seksiRows);
            $sheet->setCellValue("{$col}{$currentRow}", '=' . implode('+', $refs));
        }
        // % Keuangan: total realisasi sd / total pagu
        $paguTotalCell   = "B{$currentRow}";
        $realTotalCell   = "F{$currentRow}";
        $sheet->setCellValue("G{$currentRow}", "=IF({$paguTotalCell}>0,({$realTotalCell}/{$paguTotalCell})*100,0)");

        $totalStyle = [
            'font'      => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A5F']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'horizontal' => Alignment::HORIZONTAL_LEFT],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => 'FFFFFF']]],
        ];
        $sheet->getStyle("A{$currentRow}:H{$currentRow}")->applyFromArray($totalStyle);
        $sheet->getRowDimension($currentRow)->setRowHeight(22);

        // Number format on total row
        $numFmt = '#,##0';
        $pctFmt = '0.00"%"';
        foreach (['B','C','D','E','F','H'] as $col) {
            $sheet->getStyle("{$col}{$currentRow}")->getNumberFormat()->setFormatCode($numFmt);
            $sheet->getStyle("{$col}{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }
        $sheet->getStyle("G{$currentRow}")->getNumberFormat()->setFormatCode($pctFmt);
        $sheet->getStyle("G{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        // ---- Freeze panes ----
        $sheet->freezePane('A7');

        // ---- Output ----
        $filename = 'Laporan_Realisasi_CDK_Bojonegoro_' . $namaBulan . '_' . $tahun . '.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    // -------------------------------------------------------
    // Helper: init a node with zeroed numeric fields
    // -------------------------------------------------------
    private function initNode(string $type, string $kode, string $nama): array {
        return [
            'type'                 => $type,
            'kode'                 => $kode,
            'nama'                 => $nama,
            'pagu'                 => 0,
            'rak_saat_ini'         => 0,
            'realisasi_saat_ini'   => 0,
            'realisasi_sebelumnya' => 0,
            'realisasi_sd'         => 0,
            'jumlah_realisasi'     => 0,
            'sisa'                 => 0,
            'pct_keuangan'         => 0,
            'children'             => [],
        ];
    }

    // -------------------------------------------------------
    // Helper: accumulate numeric values upwards
    // -------------------------------------------------------
    private function accum(array &$parent, array $child): void {
        $parent['pagu']                 += $child['pagu'];
        $parent['rak_saat_ini']         += $child['rak_saat_ini'];
        $parent['realisasi_saat_ini']   += $child['realisasi_saat_ini'];
        $parent['realisasi_sebelumnya'] += $child['realisasi_sebelumnya'];
        $parent['realisasi_sd']         += $child['realisasi_sd'];
        $parent['jumlah_realisasi']     += $child['jumlah_realisasi'];
        $parent['sisa']                 += $child['sisa'];
        $parent['pct_keuangan'] = $parent['pagu'] > 0
            ? ($parent['jumlah_realisasi'] / $parent['pagu']) * 100
            : 0;
    }

    // -------------------------------------------------------
    // Helper: write one row with styling
    // -------------------------------------------------------
    private function writeRow(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, int $row, array $node, string $type): void {
        $indent = match($type) {
            'seksi'       => '',
            'program'     => '  ',
            'kegiatan'    => '    ',
            'sub_kegiatan'=> '      ',
            'rekening'    => '        ',
            default       => '',
        };

        $label = $indent . ($node['kode'] ? $node['kode'] . '   ' : '') . $node['nama'];

        $sheet->setCellValue("A{$row}", $label);
        $sheet->setCellValue("B{$row}", $node['pagu']);
        $sheet->setCellValue("C{$row}", $node['realisasi_saat_ini']);
        $sheet->setCellValue("D{$row}", $node['realisasi_sebelumnya']);
        $sheet->setCellValue("E{$row}", $node['realisasi_sd']);
        $sheet->setCellValue("F{$row}", $node['jumlah_realisasi']);
        $sheet->setCellValue("G{$row}", $node['pct_keuangan']);
        $sheet->setCellValue("H{$row}", $node['sisa']);

        // Base style
        $bgColor = match($type) {
            'seksi'        => 'D6E4F0',  // Light blue - Seksi header
            'program'      => 'E8F5E9',  // Light green - Program
            'kegiatan'     => 'FFF9E6',  // Light yellow - Kegiatan
            'sub_kegiatan' => 'F3F3F3',  // Light gray - Sub Kegiatan
            'rekening'     => 'FFFFFF',  // White - Rekening
            default        => 'FFFFFF',
        };

        $bold = in_array($type, ['seksi', 'program', 'kegiatan']);
        $fontSize = match($type) {
            'seksi'   => 10,
            'program' => 9,
            default   => 9,
        };

        $style = [
            'font'      => ['bold' => $bold, 'size' => $fontSize],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bgColor]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => false],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CCCCCC']]],
        ];

        $sheet->getStyle("A{$row}:H{$row}")->applyFromArray($style);
        $sheet->getRowDimension($row)->setRowHeight(18);

        $this->applyNumberFormat($sheet, $row);
    }

    // -------------------------------------------------------
    // Helper: apply number formats to B-I columns
    // -------------------------------------------------------
    private function applyNumberFormat(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, int $row): void {
        $numFmt = '#,##0';
        $pctFmt = '0.00"%"';

        foreach (['B','C','D','E','F','H'] as $col) {
            $sheet->getStyle("{$col}{$row}")->getNumberFormat()->setFormatCode($numFmt);
            $sheet->getStyle("{$col}{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }
        $sheet->getStyle("G{$row}")->getNumberFormat()->setFormatCode($pctFmt);
        $sheet->getStyle("G{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    }

    /**
     * Export Serapan per Sub Kegiatan per Bulan to Excel
     * Matches the monthly absorption table displayed on the dashboard.
     */
    public function exportSerapanBulanan(): void {
        $tahun        = isset($_GET['tahun'])            ? (int) $_GET['tahun']            : (int) date('Y');
        $seksiId      = isset($_GET['seksi_id'])         && $_GET['seksi_id']         !== '' ? (int) $_GET['seksi_id']         : null;
        $programId    = isset($_GET['program_id'])       && $_GET['program_id']       !== '' ? (int) $_GET['program_id']       : null;
        $kegiatanId   = isset($_GET['kegiatan_id'])      && $_GET['kegiatan_id']      !== '' ? (int) $_GET['kegiatan_id']      : null;
        $subKegId     = isset($_GET['sub_kegiatan_id'])  && $_GET['sub_kegiatan_id']  !== '' ? (int) $_GET['sub_kegiatan_id']  : null;

        $bulanNamesLong = [1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',
                           7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'];
        $bulanNamesShort = [1=>'Jan',2=>'Feb',3=>'Mar',4=>'Apr',5=>'Mei',6=>'Jun',
                            7=>'Jul',8=>'Agu',9=>'Sep',10=>'Okt',11=>'Nov',12=>'Des'];

        // -------------------------------------------------------
        // 1. Build monthly absorption data (same logic as dashboard)
        // -------------------------------------------------------
        $pagus           = $this->paguModel->getAll();
        $subKegiatanMap  = [];
        $totals = [
            'pagu'      => 0,
            'realisasi' => 0,
            'months'    => array_fill(1, 12, 0),
        ];

        foreach ($pagus as $pagu) {
            if ($pagu['tahun'] != $tahun) continue;

            // Apply filters
            if ($seksiId   !== null && ($pagu['sub_kegiatan_seksi_id'] ?? 0) != $seksiId)   continue;
            if ($programId !== null && ($pagu['program_id'] ?? 0)             != $programId) continue;
            if ($kegiatanId !== null && ($pagu['kegiatan_id'] ?? 0)           != $kegiatanId) continue;
            if ($subKegId  !== null && ($pagu['sub_kegiatan_id'] ?? 0)        != $subKegId)  continue;

            $skid        = (int) $pagu['sub_kegiatan_id'];
            $rid         = (int) $pagu['rekening_id'];
            $paguRekening = (float) $pagu['nilai_pagu'];

            if (!isset($subKegiatanMap[$skid])) {
                $subKegiatanMap[$skid] = [
                    'kode'      => $pagu['kode_sub_kegiatan'] ?? '',
                    'nama'      => $pagu['nama_sub_kegiatan'] ?? 'Tidak Diketahui',
                    'pagu'      => 0,
                    'realisasi' => 0,
                    'months'    => array_fill(1, 12, 0),
                    'rekening'  => [],
                ];
            }

            $rekeningMonths = array_fill(1, 12, 0);
            $transaksis     = $this->getTransaksiByRekeningAndYear($rid, $tahun);
            foreach ($transaksis as $t) {
                $bulan = (int) date('n', strtotime($t['tanggal']));
                if ($bulan >= 1 && $bulan <= 12) {
                    $rekeningMonths[$bulan] += (float) $t['nilai'];
                }
            }

            $realisasiRekening = array_sum($rekeningMonths);

            $subKegiatanMap[$skid]['rekening'][$rid] = [
                'kode'      => $pagu['kode_rekening'] ?? '',
                'nama'      => $pagu['nama_rekening'] ?? 'Tidak Diketahui',
                'pagu'      => $paguRekening,
                'realisasi' => $realisasiRekening,
                'months'    => $rekeningMonths,
            ];

            $subKegiatanMap[$skid]['pagu']      += $paguRekening;
            $subKegiatanMap[$skid]['realisasi'] += $realisasiRekening;
            $totals['pagu']      += $paguRekening;
            $totals['realisasi'] += $realisasiRekening;

            for ($b = 1; $b <= 12; $b++) {
                $subKegiatanMap[$skid]['months'][$b] += $rekeningMonths[$b];
                $totals['months'][$b]                += $rekeningMonths[$b];
            }
        }

        uasort($subKegiatanMap, fn($a, $b) => strcmp($a['kode'], $b['kode']));
        foreach ($subKegiatanMap as &$sk) {
            uasort($sk['rekening'], fn($a, $b) => strcmp($a['kode'], $b['kode']));
        }
        unset($sk);

        // -------------------------------------------------------
        // 2. Build Spreadsheet
        // -------------------------------------------------------
        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Serapan Bulanan');

        // Column widths: A=name, B=pagu, C..N=Jan..Des, O=Total, P=%
        $sheet->getColumnDimension('A')->setWidth(48);
        $sheet->getColumnDimension('B')->setWidth(20);
        foreach (range('C', 'N') as $col) {
            $sheet->getColumnDimension($col)->setWidth(15);
        }
        $sheet->getColumnDimension('O')->setWidth(20);
        $sheet->getColumnDimension('P')->setWidth(10);

        $lastCol = 'P'; // 16 columns total

        // ---- Row 1: Title ----
        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->setCellValue('A1', 'SERAPAN PER SUB KEGIATAN DAN REKENING PER BULAN — TAHUN ' . $tahun);
        $sheet->getStyle('A1')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 13, 'color' => ['rgb' => '1E3A5F']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(28);

        // ---- Row 2: Subtitle ----
        $sheet->mergeCells("A2:{$lastCol}2");
        $sheet->setCellValue('A2', 'CDK WILAYAH BOJONEGORO');
        $sheet->getStyle('A2')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 11, 'color' => ['rgb' => '374151']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(2)->setRowHeight(20);

        // ---- Row 3: spacer ----
        $sheet->getRowDimension(3)->setRowHeight(6);

        // ---- Row 4: Column headers ----
        $headerRow = 4;
        $sheet->setCellValue('A4', 'Sub Kegiatan / Rekening');
        $sheet->setCellValue('B4', 'Pagu');
        $colIdx = 'C';
        for ($m = 1; $m <= 12; $m++) {
            $sheet->setCellValue("{$colIdx}4", $bulanNamesLong[$m]);
            $colIdx++;
        }
        $sheet->setCellValue('O4', 'Total Realisasi');
        $sheet->setCellValue('P4', '% Serapan');

        $headerStyle = [
            'font'      => ['bold' => true, 'size' => 9, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0369A1']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '075985']]],
        ];
        $sheet->getStyle("A4:{$lastCol}4")->applyFromArray($headerStyle);
        $sheet->getRowDimension(4)->setRowHeight(22);

        // ---- Row 5 onwards: data ----
        $currentRow = 5;
        $numFmt  = '#,##0';
        $pctFmt  = '0.0"%"';

        foreach ($subKegiatanMap as $skData) {
            $skPct = $skData['pagu'] > 0 ? ($skData['realisasi'] / $skData['pagu']) * 100 : 0;

            // Sub Kegiatan row (group header)
            $sheet->setCellValue("A{$currentRow}", $skData['kode'] . '   ' . $skData['nama']);
            $sheet->setCellValue("B{$currentRow}", $skData['pagu']);
            $colIdx = 'C';
            for ($m = 1; $m <= 12; $m++) {
                $sheet->setCellValue("{$colIdx}{$currentRow}", $skData['months'][$m]);
                $colIdx++;
            }
            $sheet->setCellValue("O{$currentRow}", $skData['realisasi']);
            $sheet->setCellValue("P{$currentRow}", $skPct);

            $skStyle = [
                'font'      => ['bold' => true, 'size' => 9, 'color' => ['rgb' => '0C4A6E']],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E0F2FE']],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'BAE6FD']]],
            ];
            $sheet->getStyle("A{$currentRow}:{$lastCol}{$currentRow}")->applyFromArray($skStyle);
            $sheet->getRowDimension($currentRow)->setRowHeight(18);
            $this->applyAbsorptionNumberFormat($sheet, $currentRow, $numFmt, $pctFmt);
            $currentRow++;

            // Rekening rows (children)
            foreach ($skData['rekening'] as $rek) {
                $rekPct = $rek['pagu'] > 0 ? ($rek['realisasi'] / $rek['pagu']) * 100 : 0;

                $sheet->setCellValue("A{$currentRow}", '        ' . $rek['kode'] . '   ' . $rek['nama']);
                $sheet->setCellValue("B{$currentRow}", $rek['pagu']);
                $colIdx = 'C';
                for ($m = 1; $m <= 12; $m++) {
                    $sheet->setCellValue("{$colIdx}{$currentRow}", $rek['months'][$m]);
                    $colIdx++;
                }
                $sheet->setCellValue("O{$currentRow}", $rek['realisasi']);
                $sheet->setCellValue("P{$currentRow}", $rekPct);

                $rekStyle = [
                    'font'      => ['bold' => false, 'size' => 9],
                    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFFFF']],
                    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                    'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'DDDDDD']]],
                ];
                $sheet->getStyle("A{$currentRow}:{$lastCol}{$currentRow}")->applyFromArray($rekStyle);
                $sheet->getRowDimension($currentRow)->setRowHeight(17);
                $this->applyAbsorptionNumberFormat($sheet, $currentRow, $numFmt, $pctFmt);
                $currentRow++;
            }
        }

        // ---- Total row ----
        $totalPct = $totals['pagu'] > 0 ? ($totals['realisasi'] / $totals['pagu']) * 100 : 0;
        $sheet->setCellValue("A{$currentRow}", 'TOTAL KESELURUHAN');
        $sheet->setCellValue("B{$currentRow}", $totals['pagu']);
        $colIdx = 'C';
        for ($m = 1; $m <= 12; $m++) {
            $sheet->setCellValue("{$colIdx}{$currentRow}", $totals['months'][$m]);
            $colIdx++;
        }
        $sheet->setCellValue("O{$currentRow}", $totals['realisasi']);
        $sheet->setCellValue("P{$currentRow}", $totalPct);

        $totalStyle = [
            'font'      => ['bold' => true, 'size' => 9, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A5F']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => 'FFFFFF']]],
        ];
        $sheet->getStyle("A{$currentRow}:{$lastCol}{$currentRow}")->applyFromArray($totalStyle);
        $sheet->getRowDimension($currentRow)->setRowHeight(20);
        $this->applyAbsorptionNumberFormat($sheet, $currentRow, $numFmt, $pctFmt);

        // ---- Freeze header ----
        $sheet->freezePane('A5');

        // ---- Output ----
        $filename = 'Serapan_Bulanan_CDK_Bojonegoro_' . $tahun . '.xlsx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    /**
     * Apply number formats to columns B–O and percentage format to P
     * for the monthly absorption export rows.
     */
    private function applyAbsorptionNumberFormat(
        \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet,
        int $row,
        string $numFmt,
        string $pctFmt
    ): void {
        // B = Pagu, C..N = months, O = Total
        $sheet->getStyle("B{$row}")->getNumberFormat()->setFormatCode($numFmt);
        $sheet->getStyle("B{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $colIdx = 'C';
        for ($m = 1; $m <= 12; $m++) {
            $sheet->getStyle("{$colIdx}{$row}")->getNumberFormat()->setFormatCode($numFmt);
            $sheet->getStyle("{$colIdx}{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $colIdx++;
        }
        $sheet->getStyle("O{$row}")->getNumberFormat()->setFormatCode($numFmt);
        $sheet->getStyle("O{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle("P{$row}")->getNumberFormat()->setFormatCode($pctFmt);
        $sheet->getStyle("P{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    }

    /**
     * Export Sisa Dana per Semester to Excel.
     * Columns: Sub Kegiatan/Rekening | RAK S1 | Real S1 | Sisa S1 | RAK S2 | Real S2 | Sisa S2 | Total Sisa
     */
    public function exportSisaSemester(): void {
        $tahun      = isset($_GET['tahun'])           ? (int) $_GET['tahun']           : (int) date('Y');
        $seksiId    = isset($_GET['seksi_id'])        && $_GET['seksi_id']        !== '' ? (int) $_GET['seksi_id']        : null;
        $programId  = isset($_GET['program_id'])      && $_GET['program_id']      !== '' ? (int) $_GET['program_id']      : null;
        $kegiatanId = isset($_GET['kegiatan_id'])     && $_GET['kegiatan_id']     !== '' ? (int) $_GET['kegiatan_id']     : null;
        $subKegId   = isset($_GET['sub_kegiatan_id']) && $_GET['sub_kegiatan_id'] !== '' ? (int) $_GET['sub_kegiatan_id'] : null;

        // -------------------------------------------------------
        // 1. Build semester rekap data (mirrors DashboardController::getSemesterRekapData)
        // -------------------------------------------------------
        $pagus          = $this->paguModel->getAll();
        $subKegiatanMap = [];
        $totals = [
            'rak_s1'  => 0, 'real_s1' => 0, 'sisa_s1' => 0,
            'rak_s2'  => 0, 'real_s2' => 0, 'sisa_s2' => 0,
            'carry_over' => 0,
        ];

        foreach ($pagus as $pagu) {
            if ($pagu['tahun'] != $tahun) continue;

            // Apply filters
            if ($seksiId    !== null && ($pagu['sub_kegiatan_seksi_id'] ?? 0) != $seksiId)    continue;
            if ($programId  !== null && ($pagu['program_id']            ?? 0) != $programId)  continue;
            if ($kegiatanId !== null && ($pagu['kegiatan_id']           ?? 0) != $kegiatanId) continue;
            if ($subKegId   !== null && ($pagu['sub_kegiatan_id']       ?? 0) != $subKegId)   continue;

            $skid   = (int) $pagu['sub_kegiatan_id'];
            $rid    = (int) $pagu['rekening_id'];
            $skNama = $pagu['nama_sub_kegiatan'] ?? 'Unknown';
            $skKode = $pagu['kode_sub_kegiatan'] ?? '';
            $rNama  = $pagu['nama_rekening']     ?? 'Unknown';
            $rKode  = $pagu['kode_rekening']     ?? '';

            // RAK per semester (cached batch)
            $raks  = $this->getRakByRekeningAndYear($rid, $tahun);
            $rakS1 = 0; $rakS2 = 0;
            foreach ($raks as $r) {
                $b   = (int)   $r['bulan'];
                $val = (float) $r['nilai_rak'];
                if ($b >= 1 && $b <= 6)       $rakS1 += $val;
                elseif ($b >= 7 && $b <= 12)  $rakS2 += $val;
            }

            // Realisasi per semester (cached batch)
            $transaksis = $this->getTransaksiByRekeningAndYear($rid, $tahun);
            $realS1 = 0; $realS2 = 0;
            foreach ($transaksis as $t) {
                $b   = (int)   date('n', strtotime($t['tanggal']));
                $val = (float) $t['nilai'];
                if ($b >= 1 && $b <= 6)       $realS1 += $val;
                elseif ($b >= 7 && $b <= 12)  $realS2 += $val;
            }

            // Skip rekening tanpa RAK
            if ($rakS1 == 0 && $rakS2 == 0) continue;

            $sisaS1        = $rakS1 - $realS1;
            $sisaS2        = $rakS2 - $realS2;
            $carryOver     = max(0, $sisaS1);
            $sisaS2Efektif = $rakS2 + $carryOver - $realS2;
            $sisaTotal     = $sisaS1 + $sisaS2;

            if (!isset($subKegiatanMap[$skid])) {
                $subKegiatanMap[$skid] = [
                    'kode' => $skKode, 'nama' => $skNama,
                    'rekening'   => [],
                    'rak_s1'     => 0, 'real_s1' => 0, 'sisa_s1' => 0,
                    'rak_s2'     => 0, 'real_s2' => 0, 'sisa_s2' => 0,
                    'carry_over' => 0, 'sisa_total' => 0,
                ];
            }

            $subKegiatanMap[$skid]['rekening'][$rid] = [
                'kode'       => $rKode, 'nama' => $rNama,
                'rak_s1'     => $rakS1,       'real_s1'    => $realS1,    'sisa_s1'   => $sisaS1,
                'rak_s2'     => $rakS2,       'real_s2'    => $realS2,    'sisa_s2'   => $sisaS2Efektif,
                'carry_over' => $carryOver,   'sisa_total' => $sisaTotal,
            ];

            $subKegiatanMap[$skid]['rak_s1']     += $rakS1;
            $subKegiatanMap[$skid]['real_s1']    += $realS1;
            $subKegiatanMap[$skid]['sisa_s1']    += $sisaS1;
            $subKegiatanMap[$skid]['rak_s2']     += $rakS2;
            $subKegiatanMap[$skid]['real_s2']    += $realS2;
            $subKegiatanMap[$skid]['sisa_s2']    += $sisaS2Efektif;
            $subKegiatanMap[$skid]['carry_over'] += $carryOver;
            $subKegiatanMap[$skid]['sisa_total'] += $sisaTotal;

            $totals['rak_s1']     += $rakS1;
            $totals['real_s1']    += $realS1;
            $totals['sisa_s1']    += $sisaS1;
            $totals['rak_s2']     += $rakS2;
            $totals['real_s2']    += $realS2;
            $totals['sisa_s2']    += $sisaS2Efektif;
            $totals['carry_over'] += $carryOver;
        }

        uasort($subKegiatanMap, fn($a, $b) => strcmp($a['kode'], $b['kode']));
        foreach ($subKegiatanMap as &$sk) {
            uasort($sk['rekening'], fn($a, $b) => strcmp($a['kode'], $b['kode']));
        }
        unset($sk);

        // -------------------------------------------------------
        // 2. Build Spreadsheet
        // -------------------------------------------------------
        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Sisa Dana Semester');

        // Columns: A=name, B=RAK S1, C=Real S1, D=Sisa S1, E=RAK S2, F=Real S2, G=Sisa S2, H=Total Sisa
        $colWidths = ['A' => 52, 'B' => 20, 'C' => 20, 'D' => 20,
                      'E' => 20, 'F' => 20, 'G' => 20, 'H' => 22];
        foreach ($colWidths as $col => $w) {
            $sheet->getColumnDimension($col)->setWidth($w);
        }
        $lastCol = 'H';

        // ---- Row 1: Title ----
        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->setCellValue('A1', 'SISA DANA PER SEMESTER — TAHUN ' . $tahun);
        $sheet->getStyle('A1')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 13, 'color' => ['rgb' => '1E3A5F']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(28);

        // ---- Row 2: Subtitle ----
        $sheet->mergeCells("A2:{$lastCol}2");
        $sheet->setCellValue('A2', 'CDK WILAYAH BOJONEGORO');
        $sheet->getStyle('A2')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 11, 'color' => ['rgb' => '374151']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(2)->setRowHeight(20);

        // ---- Row 3: Note ----
        $sheet->mergeCells("A3:{$lastCol}3");
        $sheet->setCellValue('A3',
            'Keterangan: Sisa = RAK Semester - Realisasi Semester. ' .
            'Sisa S2* sudah termasuk carry-over dari Semester 1 (jika ada).');
        $sheet->getStyle('A3')->applyFromArray([
            'font'      => ['italic' => true, 'size' => 8, 'color' => ['rgb' => '64748B']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getRowDimension(3)->setRowHeight(14);

        // ---- Row 4: spacer ----
        $sheet->getRowDimension(4)->setRowHeight(5);

        // ---- Row 5-6: Column headers (2 rows for merged groups) ----
        $sheet->setCellValue('A5', 'Sub Kegiatan / Rekening');
        $sheet->mergeCells('A5:A6');

        $sheet->setCellValue('B5', 'SEMESTER 1 (Jan - Jun)');
        $sheet->mergeCells('B5:D5');

        $sheet->setCellValue('E5', 'SEMESTER 2 (Jul - Des)');
        $sheet->mergeCells('E5:G5');

        $sheet->setCellValue('H5', 'Total Sisa');
        $sheet->mergeCells('H5:H6');

        $sheet->setCellValue('B6', 'RAK S1');
        $sheet->setCellValue('C6', 'Realisasi S1');
        $sheet->setCellValue('D6', 'Sisa S1');
        $sheet->setCellValue('E6', 'RAK S2');
        $sheet->setCellValue('F6', 'Realisasi S2');
        $sheet->setCellValue('G6', 'Sisa S2*');

        // Style header row 5
        $sheet->getStyle('A5:H5')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 9, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A5F']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '0F2440']]],
        ]);

        // S1 sub-headers: teal/cyan
        $sheet->getStyle('B6:D6')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 9, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0369A1']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '075985']]],
        ]);

        // S2 sub-headers: indigo/violet
        $sheet->getStyle('E6:G6')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 9, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4338CA']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '3730A3']]],
        ]);

        // Total header
        $sheet->getStyle('H6')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 9, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A5F']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '0F2440']]],
        ]);

        $sheet->getRowDimension(5)->setRowHeight(20);
        $sheet->getRowDimension(6)->setRowHeight(18);

        // ---- Data rows ----
        $currentRow = 7;
        $numFmt     = '#,##0';

        foreach ($subKegiatanMap as $skData) {
            // Sub Kegiatan row
            $sheet->setCellValue("A{$currentRow}", $skData['kode'] . '   ' . $skData['nama']);
            $sheet->setCellValue("B{$currentRow}", $skData['rak_s1']);
            $sheet->setCellValue("C{$currentRow}", $skData['real_s1']);
            $sheet->setCellValue("D{$currentRow}", $skData['sisa_s1']);
            $sheet->setCellValue("E{$currentRow}", $skData['rak_s2']);
            $sheet->setCellValue("F{$currentRow}", $skData['real_s2']);
            $sheet->setCellValue("G{$currentRow}", $skData['sisa_s2']);
            $sheet->setCellValue("H{$currentRow}", $skData['sisa_total']);

            $sheet->getStyle("A{$currentRow}:H{$currentRow}")->applyFromArray([
                'font'      => ['bold' => true, 'size' => 9, 'color' => ['rgb' => '0C4A6E']],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E0F2FE']],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'BAE6FD']]],
            ]);
            $sheet->getRowDimension($currentRow)->setRowHeight(18);
            $this->applySisaNumberFormat($sheet, $currentRow, $numFmt,
                $skData['sisa_s1'], $skData['sisa_s2'], $skData['sisa_total']);
            $currentRow++;

            // Rekening rows
            foreach ($skData['rekening'] as $rek) {
                $sheet->setCellValue("A{$currentRow}", '        ' . $rek['kode'] . '   ' . $rek['nama']);
                $sheet->setCellValue("B{$currentRow}", $rek['rak_s1']);
                $sheet->setCellValue("C{$currentRow}", $rek['real_s1']);
                $sheet->setCellValue("D{$currentRow}", $rek['sisa_s1']);
                $sheet->setCellValue("E{$currentRow}", $rek['rak_s2']);
                $sheet->setCellValue("F{$currentRow}", $rek['real_s2']);
                $sheet->setCellValue("G{$currentRow}", $rek['sisa_s2']);
                $sheet->setCellValue("H{$currentRow}", $rek['sisa_total']);

                // Add carry-over note as cell comment if > 0
                if ($rek['carry_over'] > 0) {
                    $carryNote = 'Termasuk carry-over sisa S1: Rp ' .
                        number_format($rek['carry_over'], 0, ',', '.');
                    $sheet->getComment("G{$currentRow}")->getText()->createTextRun($carryNote);
                }

                $sheet->getStyle("A{$currentRow}:H{$currentRow}")->applyFromArray([
                    'font'      => ['bold' => false, 'size' => 9],
                    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFFFF']],
                    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                    'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E2E8F0']]],
                ]);
                $sheet->getRowDimension($currentRow)->setRowHeight(17);
                $this->applySisaNumberFormat($sheet, $currentRow, $numFmt,
                    $rek['sisa_s1'], $rek['sisa_s2'], $rek['sisa_total']);
                $currentRow++;
            }
        }

        // ---- Total row ----
        $totalSisaKeseluruhan = $totals['sisa_s1'] + $totals['sisa_s2'];
        $sheet->setCellValue("A{$currentRow}", 'TOTAL KESELURUHAN');
        $sheet->setCellValue("B{$currentRow}", $totals['rak_s1']);
        $sheet->setCellValue("C{$currentRow}", $totals['real_s1']);
        $sheet->setCellValue("D{$currentRow}", $totals['sisa_s1']);
        $sheet->setCellValue("E{$currentRow}", $totals['rak_s2']);
        $sheet->setCellValue("F{$currentRow}", $totals['real_s2']);
        $sheet->setCellValue("G{$currentRow}", $totals['sisa_s2']);
        $sheet->setCellValue("H{$currentRow}", $totalSisaKeseluruhan);

        $sheet->getStyle("A{$currentRow}:H{$currentRow}")->applyFromArray([
            'font'      => ['bold' => true, 'size' => 9, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A5F']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => 'FFFFFF']]],
        ]);
        $sheet->getRowDimension($currentRow)->setRowHeight(20);
        $this->applySisaNumberFormat($sheet, $currentRow, $numFmt,
            $totals['sisa_s1'], $totals['sisa_s2'], $totalSisaKeseluruhan);

        // ---- Freeze header ----
        $sheet->freezePane('A7');

        // ---- Output ----
        $filename = 'Sisa_Dana_Semester_CDK_Bojonegoro_' . $tahun . '.xlsx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    /**
     * Apply number formats for sisa dana semester rows.
     * Sisa columns (D, G, H) get conditional color: green=positif, red=negatif, gray=nol.
     */
    private function applySisaNumberFormat(
        \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet,
        int $row,
        string $numFmt,
        float $sisaS1,
        float $sisaS2,
        float $sisaTotal
    ): void {
        foreach (['B', 'C', 'D', 'E', 'F', 'G', 'H'] as $col) {
            $sheet->getStyle("{$col}{$row}")->getNumberFormat()->setFormatCode($numFmt);
            $sheet->getStyle("{$col}{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }

        // Conditional color for Sisa columns
        $colorMap = ['D' => $sisaS1, 'G' => $sisaS2, 'H' => $sisaTotal];
        foreach ($colorMap as $col => $val) {
            $rgb = $val > 0 ? '059669' : ($val < 0 ? 'DC2626' : '94A3B8');
            $sheet->getStyle("{$col}{$row}")->getFont()->getColor()->setRGB($rgb);
            $sheet->getStyle("{$col}{$row}")->getFont()->setBold(true);
        }
    }

    // ──────────────────────────────────────────────────────────
    // BATCH CACHING HELPERS (OPTIMASI N+1 QUERY EXCEL)
    // ──────────────────────────────────────────────────────────

    private array $rakByYearCache = [];
    private array $transaksiByYearCache = [];

    private function loadRakByYear(int $tahun): void {
        if (isset($this->rakByYearCache[$tahun])) return;
        $db = \Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM rak WHERE tahun = :tahun ORDER BY bulan ASC");
        $stmt->execute([':tahun' => $tahun]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $byRekening = [];
        foreach ($rows as $r) {
            $byRekening[(int)$r['rekening_id']][] = $r;
        }
        $this->rakByYearCache[$tahun] = $byRekening;
    }

    private function getRakByRekeningAndYear(int $rekeningId, int $tahun): array {
        $this->loadRakByYear($tahun);
        return $this->rakByYearCache[$tahun][$rekeningId] ?? [];
    }

    private function loadTransaksiByYear(int $tahun): void {
        if (isset($this->transaksiByYearCache[$tahun])) return;
        $db = \Database::getConnection();
        try {
            $stmt = $db->prepare("
                SELECT * FROM transaksi 
                WHERE YEAR(tanggal) = :tahun
                AND status = 'diverifikasi'
                ORDER BY tanggal DESC
            ");
            $stmt->execute([':tahun' => $tahun]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            $stmt = $db->prepare("
                SELECT * FROM transaksi 
                WHERE YEAR(tanggal) = :tahun
                ORDER BY tanggal DESC
            ");
            $stmt->execute([':tahun' => $tahun]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        }
        $byRekening = [];
        foreach ($rows as $t) {
            $byRekening[(int)$t['rekening_id']][] = $t;
        }
        $this->transaksiByYearCache[$tahun] = $byRekening;
    }

    private function getTransaksiByRekeningAndYear(int $rekeningId, int $tahun): array {
        $this->loadTransaksiByYear($tahun);
        return $this->transaksiByYearCache[$tahun][$rekeningId] ?? [];
    }
}
