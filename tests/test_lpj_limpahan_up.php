<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/load_env.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\LpjLimpahanUpExportService;
use App\Services\LpjLimpahanUpService;

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException('GAGAL: ' . $message);
    }
    echo 'OK: ' . $message . PHP_EOL;
}

echo "=== TEST LAPORAN PERTANGGUNGJAWABAN LIMPAHAN UP ===\n";

$db = Database::getConnection();
$db->beginTransaction();
$pid = null;

try {
    $rekeningIds = $db->query("
        SELECT p.rekening_id
        FROM pagu p
        WHERE p.tahun = 2026 AND p.nilai_pagu > 0
          AND NOT EXISTS (
            SELECT 1 FROM transaksi t
            WHERE t.rekening_id = p.rekening_id
              AND t.status = 'diverifikasi' AND t.sumber_dana = 'UP'
              AND YEAR(COALESCE(t.tanggal_lunas_dibayar, DATE(t.diverifikasi_at), t.tanggal)) = 2026
          )
        ORDER BY p.rekening_id
        LIMIT 2
    ")->fetchAll(PDO::FETCH_COLUMN);

    if (count($rekeningIds) < 2) {
        throw new RuntimeException('Butuh minimal 2 rekening tanpa transaksi UP 2026 untuk pengujian.');
    }
    [$rekA, $rekB] = array_map('intval', $rekeningIds);

    $insert = $db->prepare("
        INSERT INTO transaksi
            (tanggal, tanggal_lunas_dibayar, seksi_id, rekening_id, uraian, nilai, nomor_bukti,
             status, sumber_dana, input_by, diverifikasi_by, diverifikasi_at)
        VALUES
            (:tanggal, :lunas, 1, :rekening, :uraian, :nilai, :bukti,
             'diverifikasi', :sumber, 1, 1, :verif_at)
    ");

    $seed = [
        ['2026-10-05', 50000, $rekA, 'Akumulasi non-SPJ', 'UP'],
        ['2026-10-10', 100000, $rekA, 'Belanja SPJ rekening A', 'UP'],
        ['2026-10-15', 250000, $rekB, 'Belanja SPJ rekening B', 'UP'],
        ['2026-10-12', 70000, $rekA, 'Belanja LS rekening A', 'LS'],
    ];
    $ids = [];
    foreach ($seed as $i => [$tanggal, $nilai, $rekening, $uraian, $sumber]) {
        $insert->execute([
            ':tanggal' => $tanggal,
            ':lunas' => $tanggal,
            ':rekening' => $rekening,
            ':uraian' => $uraian,
            ':nilai' => $nilai,
            ':bukti' => 'TEST-LPJ-' . $i . '-' . bin2hex(random_bytes(3)),
            ':sumber' => $sumber,
            ':verif_at' => $tanggal . ' 09:00:00',
        ]);
        $ids[$i] = (int) $db->lastInsertId();
    }

    $db->prepare("
        INSERT INTO pengajuan_gu
            (nomor_pengajuan, tanggal_pengajuan, keterangan, total_nominal, status, created_by)
        VALUES
            (:nomor, '2026-10-19', 'Pengujian LPJ', 350000, 'diajukan', 1)
    ")->execute([':nomor' => 'TEST-LPJ/' . bin2hex(random_bytes(3))]);
    $pid = (int) $db->lastInsertId();

    $db->prepare('UPDATE transaksi SET pengajuan_gu_id = :pid WHERE id IN (:a, :b)')
        ->execute([':pid' => $pid, ':a' => $ids[1], ':b' => $ids[2]]);

    $service = new LpjLimpahanUpService($db);
    $report = $service->build($pid);

    check($report !== null, 'Laporan per pengajuan berhasil dibuat.');
    check((int) $report['meta']['jumlah_transaksi'] === 2, 'Hanya 2 transaksi pengajuan yang masuk periode.');
    check(abs($report['totals']['periode'] - 350000) < 0.001, 'Total belanja periode = Rp350.000.');

    $rekRows = [];
    foreach ($report['rows'] as $row) {
        if (($row['type'] ?? '') === 'rekening') {
            $rekRows[(int) $row['rekening_id']] = $row;
        }
    }

    check(isset($rekRows[$rekA], $rekRows[$rekB]), 'Kedua rekening muncul sebagai baris rekening.');
    check(abs($rekRows[$rekA]['periode'] - 100000) < 0.001, 'Belanja periode rekening A = Rp100.000.');
    check(abs($rekRows[$rekB]['periode'] - 250000) < 0.001, 'Belanja periode rekening B = Rp250.000.');
    check(abs($rekRows[$rekA]['akumulasi'] - 220000) < 0.001, 'Akumulasi rekening A mencakup UP non-SPJ dan LS = Rp220.000.');
    check(abs($rekRows[$rekB]['akumulasi'] - 250000) < 0.001, 'Akumulasi rekening B = Rp250.000.');

    foreach ($rekRows as $row) {
        check(
            abs($row['sisa'] - ($row['pagu'] - $row['akumulasi'])) < 0.001,
            'Sisa anggaran rekening ' . $row['kode'] . ' = pagu - akumulasi.'
        );
    }

    $period = $service->build(null, '2026-10-01', '2026-10-31');
    check($period !== null, 'Laporan rekap periode berhasil dibuat.');
    check(abs($period['totals']['periode'] - 350000) < 0.001, 'Rekap periode hanya menjumlah transaksi SPJ = Rp350.000.');
    check((int) $period['meta']['tahun'] === 2026, 'Tahun anggaran rekap periode = 2026.');
    check($period['meta']['cutoff'] === '2026-10-31', 'Cutoff akumulasi mengikuti batas akhir periode.');

    $export = new LpjLimpahanUpExportService();
    $sheet = $export->createSpreadsheet($report)->getActiveSheet();
    check($sheet->getCell('A1')->getValue() === $report['judul'], 'Judul laporan ada di sel A1 Excel.');
    check($sheet->getCell('A6')->getValue() === 'Kode Rekening', 'Header kolom Excel sesuai.');
    check($sheet->getCell('C6')->getValue() === 'Jumlah Anggaran', 'Header jumlah anggaran sesuai.');
    check($sheet->getCell('A' . $sheet->getHighestRow())->getValue() === 'TOTAL', 'Baris TOTAL ada paling bawah.');

    $accountCodes = array_map(static fn(array $row): string => (string) $row['kode'], $rekRows);
    $accountRows = [];
    for ($r = 7; $r < $sheet->getHighestRow(); $r++) {
        if (in_array((string) $sheet->getCell('A' . $r)->getValue(), $accountCodes, true)) {
            $accountRows[] = $r;
        }
    }
    check(count($accountRows) >= 2, 'Ada minimal dua baris rekening untuk uji warna.');
    $fillA = $sheet->getStyle('A' . $accountRows[0])->getFill()->getStartColor()->getRGB();
    $fillB = $sheet->getStyle('A' . $accountRows[1])->getFill()->getStartColor()->getRGB();
    check($fillA !== $fillB, 'Baris rekening memakai warna bergantian (variasi warna).');
    check(
        in_array($fillA, ['FCE4D6', 'F8CBAD'], true) && in_array($fillB, ['FCE4D6', 'F8CBAD'], true),
        'Warna baris rekening memakai palet peach.'
    );

    echo PHP_EOL . "SEMUA TEST LPJ LIMPAHAN UP LULUS.\n";
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    $code = 1;
} finally {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
}

if (isset($code)) {
    exit($code);
}
