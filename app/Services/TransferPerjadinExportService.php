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
 *   - Sheet "Rekap"  : ringkasan bergaya pivot per Transfer Ke. Tiap koordinator
 *                      tampil sebagai baris subtotal, diikuti baris-baris
 *                      anggotanya (siapa saja yang harus menerima dari
 *                      koordinator tsb), ditutup baris Grand Total.
 *                      Kolom: Nama Penerima | Penerimaan | Potongan |
 *                             Penerimaan Bersih.
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
     * Kelompokkan baris per Transfer Ke beserta anggotanya.
     *
     * @return array<int, array{label:string, count:int, penerimaan:float, potongan:float, bersih:float, members:array<int, array>}>
     */
    public function groupByTransferKe(array $rows): array
    {
        $groups = [];
        foreach ($rows as $r) {
            $label = trim((string) ($r['transfer_ke'] ?? ''));
            if ($label === '') {
                $label = '(Belum ditentukan)';
            }
            $key = self::normNama($label);
            if (!isset($groups[$key])) {
                $groups[$key] = ['label' => $label, 'count' => 0, 'penerimaan' => 0.0, 'potongan' => 0.0, 'bersih' => 0.0, 'members' => []];
            }
            $nilai = (float) ($r['nilai'] ?? 0);
            $potongan = (float) ($r['potongan'] ?? 0);
            $groups[$key]['count']++;
            $groups[$key]['penerimaan'] += $nilai;
            $groups[$key]['potongan'] += $potongan;
            $groups[$key]['bersih'] += ($nilai - $potongan);

            $mNama = (string) ($r['nama'] ?? '');
            $mKey = self::normNama($mNama);
            if (!isset($groups[$key]['members'][$mKey])) {
                $groups[$key]['members'][$mKey] = ['nama' => $mNama, 'penerimaan' => 0.0, 'potongan' => 0.0, 'bersih' => 0.0];
            }
            $groups[$key]['members'][$mKey]['penerimaan'] += $nilai;
            $groups[$key]['members'][$mKey]['potongan'] += $potongan;
            $groups[$key]['members'][$mKey]['bersih'] += ($nilai - $potongan);
        }

        uksort($groups, function (string $a, string $b): int {
            $aEmpty = $a === self::normNama('(Belum ditentukan)');
            $bEmpty = $b === self::normNama('(Belum ditentukan)');
            if ($aEmpty !== $bEmpty) {
                return $aEmpty ? 1 : -1;
            }
            return strcmp($a, $b);
        });

        foreach ($groups as &$g) {
            ksort($g['members']);
            $g['members'] = array_values($g['members']);
        }
        unset($g);

        return array_values($groups);
    }

    /**
     * Pivot: kelompokkan baris per Transfer Ke.
     *
     * @return array<int, array{label:string, count:int, penerimaan:float, potongan:float, bersih:float}>
     */
    public function pivotByTransferKe(array $rows): array
    {
        $out = [];
        foreach ($this->groupByTransferKe($rows) as $g) {
            $out[] = [
                'label' => $g['label'],
                'count' => $g['count'],
                'penerimaan' => $g['penerimaan'],
                'potongan' => $g['potongan'],
                'bersih' => $g['bersih'],
            ];
        }
        return $out;
    }

    private function buildRekapSheet(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, array $rows): void
    {
        $sheet->setTitle('Rekap');

        $sheet->mergeCells('A1:D1');
        $sheet->setCellValue('A1', 'REKAP TRANSFER PERJALANAN DINAS');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle('A1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::HEADER_FILL);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getRowDimension(1)->setRowHeight(24);

        $sheet->mergeCells('A2:D2');
        $sheet->setCellValue('A2', 'Cabang Dinas Kehutanan Wilayah Bojonegoro');
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(11);
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->mergeCells('A3:D3');
        $sheet->setCellValue('A3', 'Dicetak: ' . date('d/m/Y H:i') . ' WIB — Setiap koordinator wajib meneruskan ke anggota di bawah namanya');
        $sheet->getStyle('A3')->getFont()->setSize(9)->getColor()->setRGB('64748B');
        $sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $headerRow = 5;
        $this->writeHeader($sheet, $headerRow, ['Nama Penerima', 'Penerimaan', 'Potongan', 'Penerimaan Bersih']);

        $row = $headerRow + 1;
        $grandPenerimaan = 0.0;
        $grandPotongan = 0.0;
        $grandBersih = 0.0;

        $groups = $this->groupByTransferKe($rows);
        if ($rows === []) {
            $sheet->mergeCells('A' . $row . ':D' . $row);
            $sheet->setCellValue('A' . $row, 'Belum ada transaksi perjalanan dinas yang dikonfigurasi transfernya.');
            $sheet->getStyle('A' . $row)->getFont()->setItalic(true)->getColor()->setRGB('64748B');
            $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        } else {
            foreach ($groups as $g) {
                // Baris koordinator: subtotal per Transfer Ke.
                $sheet->setCellValue('A' . $row, $g['label']);
                $sheet->setCellValue('B' . $row, $g['penerimaan']);
                $sheet->setCellValue('C' . $row, $g['potongan']);
                $sheet->setCellValue('D' . $row, $g['bersih']);
                $this->applyCurrency($sheet, 'B' . $row);
                $this->applyCurrency($sheet, 'C' . $row);
                $this->applyCurrency($sheet, 'D' . $row);
                $sheet->getStyle('A' . $row . ':D' . $row)->getFont()->setBold(true);
                $sheet->getStyle('A' . $row . ':D' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D8F3DC');
                $this->applyThinBorders($sheet, 'A' . $row . ':D' . $row);
                $row++;

                // Baris anggota: siapa saja yang harus menerima dari koordinator di atas.
                foreach ($g['members'] as $m) {
                    $sheet->setCellValue('A' . $row, (string) ($m['nama'] ?? ''));
                    $sheet->setCellValue('B' . $row, $m['penerimaan']);
                    $sheet->setCellValue('C' . $row, $m['potongan']);
                    $sheet->setCellValue('D' . $row, $m['bersih']);
                    $this->applyCurrency($sheet, 'B' . $row);
                    $this->applyCurrency($sheet, 'C' . $row);
                    $this->applyCurrency($sheet, 'D' . $row);
                    $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setIndent(2);
                    $this->applyThinBorders($sheet, 'A' . $row . ':D' . $row);
                    $row++;
                }

                $grandPenerimaan += $g['penerimaan'];
                $grandPotongan += $g['potongan'];
                $grandBersih += $g['bersih'];
            }

            $sheet->setCellValue('A' . $row, 'Grand Total');
            $sheet->setCellValue('B' . $row, $grandPenerimaan);
            $sheet->setCellValue('C' . $row, $grandPotongan);
            $sheet->setCellValue('D' . $row, $grandBersih);
            $this->applyCurrency($sheet, 'B' . $row);
            $this->applyCurrency($sheet, 'C' . $row);
            $this->applyCurrency($sheet, 'D' . $row);
            $sheet->getStyle('A' . $row . ':D' . $row)->getFont()->setBold(true);
            $sheet->getStyle('A' . $row . ':D' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::TOTAL_FILL);
            $this->applyThinBorders($sheet, 'A' . $row . ':D' . $row);
        }

        foreach (['A' => 40, 'B' => 18, 'C' => 16, 'D' => 24] as $col => $width) {
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

        // Jangan biarkan notice/warning/deprecation bocor ke dalam biner.
        ini_set('display_errors', '0');
        @ini_set('zlib.output_compression', '0');

        // Buang sisa output buffer (spasi/BOM/echo nyasar sebelum unduhan).
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        // Bangun file ke memori dulu (belum ada byte dikirim ke browser),
        // sekaligus menampung output nyasar selama build agar bisa dibuang.
        $tmp = fopen('php://memory', 'w+b');
        if ($tmp === false) {
            throw new \RuntimeException('Gagal menyiapkan buffer unduhan.');
        }
        ob_start();
        try {
            $writer = new Xlsx($this->buildSpreadsheet($rows));
            $writer->save($tmp);
        } finally {
            $junk = (string) ob_get_clean();
            if ($junk !== '') {
                error_log('TransferPerjadinExportService: membuang ' . strlen($junk) . ' byte output nyasar saat build Excel.');
            }
        }

        // Validasi: file xlsx harus arsip ZIP (magic "PK"). Kalau bukan,
        // lempar error agar controller menampilkan pesan — bukan file korup.
        rewind($tmp);
        $magic = (string) fread($tmp, 2);
        $stat = fstat($tmp);
        $size = is_array($stat) ? (int) ($stat['size'] ?? 0) : 0;
        if ($magic !== 'PK' || $size <= 0) {
            fclose($tmp);
            throw new \RuntimeException('Berkas Excel gagal dibuat (bukan arsip ZIP valid).');
        }
        rewind($tmp);

        header('Content-Description: File Transfer');
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Transfer-Encoding: binary');
        header('Content-Length: ' . $size);
        header('Cache-Control: max-age=0, must-revalidate');
        header('Pragma: public');

        fpassthru($tmp);
        fclose($tmp);
        exit;
    }

    public static function safeFilename(string $s): string
    {
        $s = trim($s);
        $s = preg_replace('/[^\p{L}\p{N}._-]+/u', '_', $s) ?? 'konfigurasi';
        $s = trim($s, '._');
        if ($s === '') {
            $s = 'konfigurasi';
        }
        return mb_substr($s, 0, 80);
    }
}
