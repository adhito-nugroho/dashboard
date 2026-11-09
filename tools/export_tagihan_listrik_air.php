<?php

declare(strict_types=1);

/**
 * Export tagihan Listrik & Air per bulan ke Excel.
 *
 * Jalankan di server:
 *   php tools/export_tagihan_listrik_air.php
 *   php tools/export_tagihan_listrik_air.php --tahun=2026 --dari=3 --sampai=10
 *
 * Opsi:
 *   --tahun=2026        tahun transaksi (default 2026)
 *   --dari=3            bulan awal (default Maret)
 *   --sampai=10         bulan akhir (default Oktober)
 *   --out=folder        folder output (default storage/exports)
 *   --all-status        ikutkan transaksi selain 'diverifikasi'
 */

require_once __DIR__ . '/../config/load_env.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "Hanya bisa dijalankan dari CLI.\n";
    exit(1);
}

$tahun = 2026;
$dari = 3;
$sampai = 10;
$allStatus = false;
$outDir = __DIR__ . '/../storage/exports';

foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--tahun=')) {
        $tahun = (int) substr($arg, 8);
    } elseif (str_starts_with($arg, '--dari=')) {
        $dari = (int) substr($arg, 7);
    } elseif (str_starts_with($arg, '--sampai=')) {
        $sampai = (int) substr($arg, 9);
    } elseif (str_starts_with($arg, '--out=')) {
        $outDir = substr($arg, 6);
    } elseif ($arg === '--all-status') {
        $allStatus = true;
    }
}

if ($tahun < 2000 || $dari < 1 || $dari > 12 || $sampai < 1 || $sampai > 12 || $dari > $sampai) {
    fwrite(STDERR, "Periode tidak valid. Contoh: --tahun=2026 --dari=3 --sampai=10\n");
    exit(1);
}

$bulanMap = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
];
$labelPeriode = $dari === $sampai
    ? $bulanMap[$dari]
    : $bulanMap[$dari] . '-' . $bulanMap[$sampai];

$daftar = [
    ['jenis' => 'Listrik', 'kode' => '5.1.02.02.01.0061', 'nama' => 'Belanja Tagihan Listrik'],
    ['jenis' => 'Air', 'kode' => '5.1.02.02.01.0060', 'nama' => 'Belanja Tagihan Air'],
];

$db = Database::getConnection();

if (!is_dir($outDir) && !mkdir($outDir, 0775, true) && !is_dir($outDir)) {
    fwrite(STDERR, "Gagal membuat folder output: {$outDir}\n");
    exit(1);
}

foreach ($daftar as $item) {
    $rows = ambilTransaksi($db, $item['kode'], $tahun, $dari, $sampai, $allStatus);
    $spreadsheet = buatSpreadsheet($item, $rows, $tahun, $dari, $sampai, $allStatus, $bulanMap);
    $file = rtrim($outDir, '/\\') . DIRECTORY_SEPARATOR
        . 'Tagihan_' . $item['jenis'] . '_' . $labelPeriode . '_' . $tahun . '.xlsx';

    try {
        (new Xlsx($spreadsheet))->save($file);
    } catch (Throwable $e) {
        fwrite(STDERR, "Gagal menulis {$file}: {$e->getMessage()}\n");
        exit(1);
    }

    $total = 0.0;
    foreach ($rows as $row) {
        $total += (float) $row['nilai'];
    }
    echo sprintf(
        "%-8s : %d transaksi, total Rp %s -> %s\n",
        $item['jenis'],
        count($rows),
        number_format($total, 0, ',', '.'),
        $file
    );
}

/**
 * @return array<int, array<string, mixed>>
 */
function ambilTransaksi(\PDO $db, string $kode, int $tahun, int $dari, int $sampai, bool $allStatus): array
{
    $sql = "
        SELECT t.id, t.tanggal, t.tanggal_lunas_dibayar, t.uraian, t.nomor_bukti,
               t.nilai, t.status, s.nama_seksi
        FROM transaksi t
        INNER JOIN rekening r ON r.id = t.rekening_id
        LEFT JOIN seksi s ON s.id = t.seksi_id
        WHERE TRIM(r.kode_rekening) = :kode
          AND YEAR(t.tanggal) = :tahun
          AND MONTH(t.tanggal) BETWEEN :dari AND :sampai
    ";
    if (!$allStatus) {
        $sql .= " AND t.status = 'diverifikasi'";
    }
    $sql .= " ORDER BY t.tanggal ASC, t.id ASC";

    $stmt = $db->prepare($sql);
    $stmt->bindValue(':kode', $kode, \PDO::PARAM_STR);
    $stmt->bindValue(':tahun', $tahun, \PDO::PARAM_INT);
    $stmt->bindValue(':dari', $dari, \PDO::PARAM_INT);
    $stmt->bindValue(':sampai', $sampai, \PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(\PDO::FETCH_ASSOC);
}

/**
 * @param array<int, array<string, mixed>> $rows
 */
function buatSpreadsheet(array $item, array $rows, int $tahun, int $dari, int $sampai, bool $allStatus, array $bulanMap): Spreadsheet
{
    $spreadsheet = new Spreadsheet();
    buatRekap($spreadsheet->getActiveSheet(), $item, $rows, $tahun, $dari, $sampai, $allStatus, $bulanMap);
    buatDetail($spreadsheet->createSheet(), $item, $rows, $tahun, $dari, $sampai, $allStatus, $bulanMap);
    $spreadsheet->setActiveSheetIndex(0);

    return $spreadsheet;
}

/**
 * @param array<int, array<string, mixed>> $rows
 */
function buatRekap(Worksheet $sheet, array $item, array $rows, int $tahun, int $dari, int $sampai, bool $allStatus, array $bulanMap): void
{
    $sheet->setTitle('Rekap');

    $sheet->mergeCells('A1:D1');
    $sheet->setCellValue('A1', 'REKAP TAGIHAN ' . strtoupper((string) $item['jenis']));
    $sheet->getStyle('A1')->applyFromArray([
        'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A5F']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    ]);
    $sheet->getRowDimension(1)->setRowHeight(26);

    $sheet->mergeCells('A2:D2');
    $sheet->setCellValue('A2', 'Rekening: ' . $item['kode'] . ' — ' . $item['nama']);
    $sheet->getStyle('A2')->applyFromArray([
        'font' => ['bold' => true, 'size' => 10],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    ]);

    $sheet->mergeCells('A3:D3');
    $sheet->setCellValue(
        'A3',
        'Periode: ' . $bulanMap[$dari] . ' s.d. ' . $bulanMap[$sampai] . ' ' . $tahun
        . ' — ' . ($allStatus ? 'semua status' : 'hanya transaksi diverifikasi')
    );
    $sheet->getStyle('A3')->applyFromArray([
        'font' => ['italic' => true, 'size' => 9, 'color' => ['rgb' => '64748B']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    ]);

    $headers = ['No', 'Bulan', 'Jumlah Transaksi', 'Nominal (Rp)'];
    foreach ($headers as $i => $label) {
        $sheet->setCellValue(chr(65 + $i) . '5', $label);
    }
    $sheet->getStyle('A5:D5')->applyFromArray([
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A5F']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'FFFFFF']]],
    ]);
    $sheet->getRowDimension(5)->setRowHeight(22);

    $monthly = [];
    foreach ($rows as $row) {
        $m = (int) date('n', strtotime((string) $row['tanggal']));
        if (!isset($monthly[$m])) {
            $monthly[$m] = ['count' => 0, 'nominal' => 0.0];
        }
        $monthly[$m]['count']++;
        $monthly[$m]['nominal'] += (float) $row['nilai'];
    }

    $rowNumber = 6;
    $no = 1;
    $totalCount = 0;
    $totalNominal = 0.0;

    for ($m = $dari; $m <= $sampai; $m++) {
        $count = (int) ($monthly[$m]['count'] ?? 0);
        $nominal = (float) ($monthly[$m]['nominal'] ?? 0);

        $sheet->setCellValue('A' . $rowNumber, $no);
        $sheet->setCellValue('B' . $rowNumber, $bulanMap[$m]);
        $sheet->setCellValue('C' . $rowNumber, $count);
        $sheet->setCellValue('D' . $rowNumber, $nominal);

        $sheet->getStyle('A' . $rowNumber . ':D' . $rowNumber)->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D1D5DB']]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getStyle('A' . $rowNumber)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('C' . $rowNumber)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('D' . $rowNumber)->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getStyle('D' . $rowNumber)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        $totalCount += $count;
        $totalNominal += $nominal;
        $rowNumber++;
        $no++;
    }

    $totalRow = $rowNumber;
    $sheet->mergeCells('A' . $totalRow . ':B' . $totalRow);
    $sheet->setCellValue('A' . $totalRow, 'TOTAL');
    $sheet->setCellValue('C' . $totalRow, $totalCount);
    $sheet->setCellValue('D' . $totalRow, $totalNominal);
    $sheet->getStyle('A' . $totalRow . ':D' . $totalRow)->applyFromArray([
        'font' => ['bold' => true],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D8F3DC']],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '93C5FD']]],
    ]);
    $sheet->getStyle('A' . $totalRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('C' . $totalRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('D' . $totalRow)->getNumberFormat()->setFormatCode('#,##0');
    $sheet->getStyle('D' . $totalRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

    foreach (['A' => 5, 'B' => 24, 'C' => 20, 'D' => 22] as $col => $width) {
        $sheet->getColumnDimension($col)->setWidth($width);
    }

    $sheet->freezePane('A6');
    $sheet->getPageSetup()
        ->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_PORTRAIT)
        ->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4)
        ->setFitToWidth(1)
        ->setFitToHeight(0);
}

/**
 * @param array<int, array<string, mixed>> $rows
 */
function buatDetail(Worksheet $sheet, array $item, array $rows, int $tahun, int $dari, int $sampai, bool $allStatus, array $bulanMap): void
{
    $sheet->setTitle('Detail');

    $sheet->mergeCells('A1:H1');
    $sheet->setCellValue('A1', 'DETAIL TAGIHAN ' . strtoupper((string) $item['jenis']));
    $sheet->getStyle('A1')->applyFromArray([
        'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A5F']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    ]);
    $sheet->getRowDimension(1)->setRowHeight(26);

    $sheet->mergeCells('A2:H2');
    $sheet->setCellValue('A2', 'Rekening: ' . $item['kode'] . ' — ' . $item['nama']);
    $sheet->getStyle('A2')->applyFromArray([
        'font' => ['bold' => true, 'size' => 10],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    ]);

    $sheet->mergeCells('A3:H3');
    $sheet->setCellValue(
        'A3',
        'Periode: ' . $bulanMap[$dari] . ' s.d. ' . $bulanMap[$sampai] . ' ' . $tahun
        . ' — ' . ($allStatus ? 'semua status' : 'hanya transaksi diverifikasi')
    );
    $sheet->getStyle('A3')->applyFromArray([
        'font' => ['italic' => true, 'size' => 9, 'color' => ['rgb' => '64748B']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    ]);

    $headers = ['No', 'Tanggal', 'Tanggal Bayar', 'Uraian', 'No Bukti', 'Seksi', 'Status', 'Nominal (Rp)'];
    foreach ($headers as $i => $label) {
        $sheet->setCellValue(chr(65 + $i) . '5', $label);
    }
    $sheet->getStyle('A5:H5')->applyFromArray([
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A5F']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'FFFFFF']]],
    ]);
    $sheet->getRowDimension(5)->setRowHeight(24);

    $statusLabel = [
        'diajukan' => 'Menunggu Verifikasi',
        'diverifikasi' => 'Diverifikasi',
        'ditolak' => 'Ditolak',
    ];

    $rowNumber = 6;
    $no = 1;
    $totalNominal = 0.0;

    if ($rows === []) {
        $sheet->mergeCells('A6:H6');
        $sheet->setCellValue('A6', 'Tidak ada transaksi pada periode ini.');
        $sheet->getStyle('A6')->applyFromArray([
            'font' => ['italic' => true, 'color' => ['rgb' => '64748B']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
    } else {
        foreach ($rows as $row) {
            $tanggal = !empty($row['tanggal']) ? date('d/m/Y', strtotime((string) $row['tanggal'])) : '-';
            $tanggalBayar = !empty($row['tanggal_lunas_dibayar'])
                ? date('d/m/Y', strtotime((string) $row['tanggal_lunas_dibayar']))
                : '-';
            $status = (string) ($row['status'] ?? '');
            $nilai = (float) ($row['nilai'] ?? 0);

            $sheet->setCellValue('A' . $rowNumber, $no);
            $sheet->setCellValue('B' . $rowNumber, $tanggal);
            $sheet->setCellValue('C' . $rowNumber, $tanggalBayar);
            $sheet->setCellValue('D' . $rowNumber, (string) ($row['uraian'] ?? ''));
            $sheet->setCellValue('E' . $rowNumber, (string) ($row['nomor_bukti'] ?? '-'));
            $sheet->setCellValue('F' . $rowNumber, (string) ($row['nama_seksi'] ?? '-'));
            $sheet->setCellValue('G' . $rowNumber, $statusLabel[$status] ?? ucfirst($status));
            $sheet->setCellValue('H' . $rowNumber, $nilai);

            $sheet->getStyle('A' . $rowNumber . ':H' . $rowNumber)->applyFromArray([
                'font' => ['size' => 9],
                'alignment' => ['vertical' => Alignment::VERTICAL_TOP],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D1D5DB']]],
            ]);
            $sheet->getStyle('A' . $rowNumber)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('B' . $rowNumber)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('C' . $rowNumber)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('D' . $rowNumber)->getAlignment()->setWrapText(true);
            $sheet->getStyle('G' . $rowNumber)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('H' . $rowNumber)->getNumberFormat()->setFormatCode('#,##0');
            $sheet->getStyle('H' . $rowNumber)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

            $totalNominal += $nilai;
            $rowNumber++;
            $no++;
        }

        $totalRow = $rowNumber;
        $sheet->mergeCells('A' . $totalRow . ':G' . $totalRow);
        $sheet->setCellValue('A' . $totalRow, 'TOTAL (' . count($rows) . ' transaksi)');
        $sheet->setCellValue('H' . $totalRow, $totalNominal);
        $sheet->getStyle('A' . $totalRow . ':H' . $totalRow)->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D8F3DC']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '93C5FD']]],
        ]);
        $sheet->getStyle('A' . $totalRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('H' . $totalRow)->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getStyle('H' . $totalRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    }

    foreach (['A' => 5, 'B' => 12, 'C' => 14, 'D' => 60, 'E' => 28, 'F' => 22, 'G' => 20, 'H' => 18] as $col => $width) {
        $sheet->getColumnDimension($col)->setWidth($width);
    }

    $sheet->freezePane('A6');
    $sheet->getPageSetup()
        ->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE)
        ->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4)
        ->setFitToWidth(1)
        ->setFitToHeight(0);
}
