<?php

declare(strict_types=1);

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Service export Excel "Transfer Perjalanan Dinas".
 *
 * Menghasilkan satu file berisi beberapa Surat Tugas sekaligus:
 *   - Sheet "Rekap"  : pivot dikelompokkan per penerima transfer (Transfer Ke),
 *                      dengan subtotal per kelompok + grand total.
 *                      Kolom: Transfer Ke | Nama Peserta | Penerimaan |
 *                             Potongan | Penerimaan Bersih.
 *   - Sheet "Detail" : rincian per transaksi (uraian) yang menyusun angka rekap.
 *                      Kolom: uraian | nama | penerimaan | transfer ke |
 *                             potongan | penerimaan bersih.
 *
 * Penerimaan (gross) = jumlah transaksi perjalanan dinas pegawai. Potongan =
 * input manual admin. Penerimaan bersih = penerimaan - potongan.
 */
class TransferPerjadinExportService
{
    private const HEADER_FILL = '1B4332';
    private const GROUP_FILL  = 'D8F3DC';
    private const SUBTOTAL_FILL = 'EDF6F0';
    private const TOTAL_FILL  = 'B7E4C7';

    // ──────────────────────────────────────────────────────────
    // Pencocokan pegawai <-> transaksi
    // ──────────────────────────────────────────────────────────

    public static function normNip(?string $s): string
    {
        return preg_replace('/[^0-9]/', '', (string) $s) ?? '';
    }

    public static function normNama(?string $s): string
    {
        $s = strtoupper(trim((string) $s));
        $s = preg_replace('/\s+/', ' ', $s) ?? $s;
        $s = preg_replace('/[^A-Z0-9 ]/', '', $s) ?? $s;
        return trim($s);
    }

    /**
     * Ambil baris transaksi yang cocok dengan satu pegawai:
     * NIP ternormalisasi sama, atau nama sama, atau salah satu mengandung
     * yang lain (min. 5 karakter, untuk toleransi gelar/singkatan).
     *
     * @param array<int, array> $trxRows
     * @return array<int, array>
     */
    public static function matchLinesForPegawai(array $trxRows, string $nip, string $nama): array
    {
        $nn = self::normNip($nip);
        $nm = self::normNama($nama);
        $out = [];

        foreach ($trxRows as $t) {
            $tn = self::normNip($t['pegawai_nip'] ?? '');
            if ($nn !== '' && $tn !== '' && $nn === $tn) {
                $out[] = $t;
                continue;
            }

            $tm = self::normNama($t['nama_penerima'] ?? '');
            if ($nm === '' || $tm === '') {
                continue;
            }
            if ($nm === $tm) {
                $out[] = $t;
                continue;
            }
            if (mb_strlen($nm) >= 5 && (str_contains($tm, $nm) || str_contains($nm, $tm))) {
                $out[] = $t;
            }
        }

        return $out;
    }

    // ──────────────────────────────────────────────────────────
    // SPREADSHEET
    // ──────────────────────────────────────────────────────────

    /**
     * @param array<int, array{st:array, pegawai:array}> $stRows
     */
    public function buildSpreadsheet(array $stRows): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();

        $this->buildRekapSheet($spreadsheet->getActiveSheet(), $stRows);
        $detail = $spreadsheet->createSheet();
        $this->buildDetailSheet($detail, $stRows);

        $spreadsheet->setActiveSheetIndex(0);
        return $spreadsheet;
    }

    private function buildRekapSheet(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, array $stRows): void
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
        $headers = ['Transfer Ke', 'Nama Peserta', 'Penerimaan', 'Potongan', 'Penerimaan Bersih'];
        $this->writeHeader($sheet, $headerRow, $headers);

        $groups = $this->groupByTransferKe($stRows);

        $row = $headerRow + 1;
        $grandPenerimaan = 0.0;
        $grandPotongan = 0.0;
        $grandBersih = 0.0;

        foreach ($groups as $group) {
            $groupStart = $row;
            $gPenerimaan = 0.0;
            $gPotongan = 0.0;
            $gBersih = 0.0;
            $firstInGroup = true;

            foreach ($group['rows'] as $p) {
                $sheet->setCellValue('A' . $row, $firstInGroup ? $group['label'] : '');
                $sheet->setCellValue('B' . $row, (string) $p['nama']);
                $sheet->setCellValue('C' . $row, (float) $p['penerimaan']);
                $sheet->setCellValue('D' . $row, (float) $p['potongan']);
                $sheet->setCellValue('E' . $row, (float) $p['bersih']);
                $this->applyCurrency($sheet, 'C' . $row);
                $this->applyCurrency($sheet, 'D' . $row);
                $this->applyCurrency($sheet, 'E' . $row);
                if ($firstInGroup) {
                    $sheet->getStyle('A' . $row)->getFont()->setBold(true);
                    $sheet->getStyle('A' . $row)->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
                }
                $this->applyThinBorders($sheet, 'A' . $row . ':E' . $row);

                $gPenerimaan += (float) $p['penerimaan'];
                $gPotongan += (float) $p['potongan'];
                $gBersih += (float) $p['bersih'];
                $firstInGroup = false;
                $row++;
            }

            // Subtotal per kelompok
            $sheet->setCellValue('A' . $row, 'Subtotal');
            $sheet->setCellValue('B' . $row, $group['label']);
            $sheet->setCellValue('C' . $row, $gPenerimaan);
            $sheet->setCellValue('D' . $row, $gPotongan);
            $sheet->setCellValue('E' . $row, $gBersih);
            $this->applyCurrency($sheet, 'C' . $row);
            $this->applyCurrency($sheet, 'D' . $row);
            $this->applyCurrency($sheet, 'E' . $row);
            $sheet->getStyle('A' . $row . ':E' . $row)->getFont()->setBold(true)->setItalic(true);
            $sheet->getStyle('A' . $row . ':E' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::SUBTOTAL_FILL);
            $this->applyThinBorders($sheet, 'A' . $row . ':E' . $row);

            $grandPenerimaan += $gPenerimaan;
            $grandPotongan += $gPotongan;
            $grandBersih += $gBersih;
            $row++;
        }

        if ($groups === []) {
            $sheet->mergeCells('A' . $row . ':E' . $row);
            $sheet->setCellValue('A' . $row, 'Belum ada Surat Tugas yang dikonfigurasi transfernya.');
            $sheet->getStyle('A' . $row)->getFont()->setItalic(true)->getColor()->setRGB('64748B');
            $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $row++;
        } else {
            $sheet->mergeCells('A' . $row . ':B' . $row);
            $sheet->setCellValue('A' . $row, 'GRAND TOTAL');
            $sheet->setCellValue('C' . $row, $grandPenerimaan);
            $sheet->setCellValue('D' . $row, $grandPotongan);
            $sheet->setCellValue('E' . $row, $grandBersih);
            $this->applyCurrency($sheet, 'C' . $row);
            $this->applyCurrency($sheet, 'D' . $row);
            $this->applyCurrency($sheet, 'E' . $row);
            $sheet->getStyle('A' . $row . ':E' . $row)->getFont()->setBold(true);
            $sheet->getStyle('A' . $row . ':E' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::TOTAL_FILL);
            $this->applyThinBorders($sheet, 'A' . $row . ':E' . $row);
        }

        foreach (['A' => 30, 'B' => 34, 'C' => 16, 'D' => 14, 'E' => 18] as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }
        $sheet->freezePane('A' . ($headerRow + 1));
    }

    private function buildDetailSheet(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, array $stRows): void
    {
        $sheet->setTitle('Detail');

        $sheet->mergeCells('A1:F1');
        $sheet->setCellValue('A1', 'DETAIL TRANSFER PERJALANAN DINAS');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle('A1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::HEADER_FILL);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getRowDimension(1)->setRowHeight(24);

        $sheet->mergeCells('A2:F2');
        $sheet->setCellValue('A2', 'Rincian uraian transaksi perjalanan dinas penyusun angka rekap');
        $sheet->getStyle('A2')->getFont()->setSize(10)->getColor()->setRGB('64748B');
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $headerRow = 4;
        $headers = ['Uraian', 'Nama', 'Penerimaan', 'Transfer Ke', 'Potongan', 'Penerimaan Bersih'];
        $this->writeHeader($sheet, $headerRow, $headers);

        $row = $headerRow + 1;

        foreach ($stRows as $s) {
            $keLabel = '-';
            foreach ($s['pegawai'] as $p) {
                if (!empty($p['is_transfer_ke'])) {
                    $keLabel = (string) $p['nama'];
                    break;
                }
            }

            foreach ($s['pegawai'] as $p) {
                $lines = $p['lines'] ?? [];
                $nama = (string) $p['nama'];

                if ($lines === []) {
                    $sheet->setCellValue('A' . $row, '(belum ada transaksi perjalanan dinas)');
                    $sheet->setCellValue('B' . $row, $nama);
                    $sheet->setCellValue('C' . $row, 0);
                    $sheet->setCellValue('D' . $row, $keLabel);
                    $sheet->setCellValue('E' . $row, (float) $p['potongan']);
                    $sheet->setCellValue('F' . $row, (float) $p['bersih']);
                    $this->styleDetailRow($sheet, $row);
                    $row++;
                    continue;
                }

                $first = true;
                foreach ($lines as $l) {
                    $sheet->setCellValue('A' . $row, (string) ($l['uraian'] ?? ''));
                    $sheet->setCellValue('B' . $row, $nama);
                    $sheet->setCellValue('C' . $row, (float) ($l['nilai'] ?? 0));
                    $sheet->setCellValue('D' . $row, $first ? $keLabel : '');
                    $sheet->setCellValue('E' . $row, $first ? (float) $p['potongan'] : 0);
                    $sheet->setCellValue('F' . $row, $first ? (float) $p['bersih'] : '');
                    $this->styleDetailRow($sheet, $row);
                    $first = false;
                    $row++;
                }
            }
        }

        if ($row === $headerRow + 1) {
            $sheet->mergeCells('A' . $row . ':F' . $row);
            $sheet->setCellValue('A' . $row, 'Belum ada data.');
            $sheet->getStyle('A' . $row)->getFont()->setItalic(true)->getColor()->setRGB('64748B');
            $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        foreach (['A' => 52, 'B' => 28, 'C' => 16, 'D' => 30, 'E' => 14, 'F' => 18] as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }
        $sheet->freezePane('A' . ($headerRow + 1));
    }

    // ──────────────────────────────────────────────────────────
    // HELPERS
    // ──────────────────────────────────────────────────────────

    /**
     * Kelompokkan baris pegawai per penerima transfer (Transfer Ke).
     * Surat Tugas tanpa penerima transfer masuk kelompok "(Belum ditentukan)".
     *
     * @return array<int, array{label:string, rows:array}>
     */
    private function groupByTransferKe(array $stRows): array
    {
        $groups = [];

        foreach ($stRows as $s) {
            $keLabel = '(Belum ditentukan)';
            foreach ($s['pegawai'] as $p) {
                if (!empty($p['is_transfer_ke'])) {
                    $keLabel = (string) $p['nama'];
                    break;
                }
            }
            $key = self::normNama($keLabel);
            if (!isset($groups[$key])) {
                $groups[$key] = ['label' => $keLabel, 'rows' => []];
            }
            foreach ($s['pegawai'] as $p) {
                $groups[$key]['rows'][] = $p;
            }
        }

        // Urutkan: kelompok "(Belum ditentukan)" paling bawah, sisanya alfabetis.
        uksort($groups, function (string $a, string $b): int {
            $aEmpty = $a === self::normNama('(Belum ditentukan)');
            $bEmpty = $b === self::normNama('(Belum ditentukan)');
            if ($aEmpty !== $bEmpty) {
                return $aEmpty ? 1 : -1;
            }
            return strcmp($a, $b);
        });

        // Dalam kelompok: penerima transfer dulu, lalu nama.
        foreach ($groups as &$g) {
            usort($g['rows'], function (array $x, array $y): int {
                $xk = !empty($x['is_transfer_ke']) ? 0 : 1;
                $yk = !empty($y['is_transfer_ke']) ? 0 : 1;
                if ($xk !== $yk) {
                    return $xk <=> $yk;
                }
                return strcmp((string) $x['nama'], (string) $y['nama']);
            });
        }
        unset($g);

        return array_values($groups);
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

    private function styleDetailRow(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, int $row): void
    {
        $this->applyCurrency($sheet, 'C' . $row);
        $sheet->getStyle('E' . $row)->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getStyle('F' . $row)->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getStyle('A' . $row)->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
        $this->applyThinBorders($sheet, 'A' . $row . ':F' . $row);
    }

    private function applyCurrency(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, string $cell): void
    {
        $sheet->getStyle($cell)->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getStyle($cell)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    }

    private function applyThinBorders(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('D1D5DB');
    }

    // ──────────────────────────────────────────────────────────
    // OUTPUT
    // ──────────────────────────────────────────────────────────

    /**
     * @param array<int, array{st:array, pegawai:array}> $stRows
     */
    public function download(array $stRows, ?string $filename = null): void
    {
        $filename = $filename ?: ('Transfer_Perjalanan_Dinas_' . date('Ymd_His') . '.xlsx');

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        header('Pragma: public');

        $writer = new Xlsx($this->buildSpreadsheet($stRows));
        $writer->save('php://output');
        exit;
    }
}
