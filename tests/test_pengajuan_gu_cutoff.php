<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/load_env.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/Models/Transaksi.php';
require_once __DIR__ . '/../app/Models/PengajuanGu.php';

use App\Models\PengajuanGu;

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException('GAGAL: ' . $message);
    }
    echo 'OK: ' . $message . PHP_EOL;
}

echo "=== TEST CUTOFF TRANSAKSI SPJ GU (MULAI 1 OKTOBER 2026) ===\n";

$db = Database::getConnection();
$db->beginTransaction();

try {
    $model = new PengajuanGu($db);
    $totalSebelum = $model->getTotalBelumSpj();

    $insert = $db->prepare("
        INSERT INTO transaksi
            (tanggal, tanggal_lunas_dibayar, seksi_id, rekening_id, uraian, nilai, nomor_bukti,
             status, sumber_dana, input_by, diverifikasi_by, diverifikasi_at)
        VALUES
            (:tanggal, :lunas, 1, 6, :uraian, :nilai, :bukti,
             'diverifikasi', 'UP', 1, 1, :verif_at)
    ");

    $seed = [
        ['2026-09-30', 111000, 'TEST-CUTOFF-SEP', 'Transaksi sebelum fitur SPJ'],
        ['2026-10-01', 222000, 'TEST-CUTOFF-OKT1', 'Transaksi tepat mulai fitur SPJ'],
        ['2026-10-02', 333000, 'TEST-CUTOFF-OKT2', 'Transaksi setelah fitur SPJ'],
    ];
    $ids = [];
    foreach ($seed as [$tanggal, $nilai, $bukti, $uraian]) {
        $insert->execute([
            ':tanggal' => $tanggal,
            ':lunas' => $tanggal,
            ':uraian' => $uraian,
            ':nilai' => $nilai,
            ':bukti' => $bukti . '-' . bin2hex(random_bytes(3)),
            ':verif_at' => $tanggal . ' 09:00:00',
        ]);
        $ids[] = (int) $db->lastInsertId();
    }

    $db->prepare("
        INSERT INTO pengajuan_gu
            (nomor_pengajuan, tanggal_pengajuan, keterangan, total_nominal, status, created_by)
        VALUES
            (:nomor, '2026-10-02', 'Pengujian cutoff', 333000, 'diajukan', 1)
    ")->execute([':nomor' => 'TEST-CUTOFF/' . bin2hex(random_bytes(3))]);
    $pid = (int) $db->lastInsertId();

    $db->prepare('UPDATE transaksi SET pengajuan_gu_id = :pid WHERE id = :id')
        ->execute([':pid' => $pid, ':id' => $ids[2]]);

    $siap = $model->getSiapSpj();
    $siapBukti = array_column($siap, 'nomor_bukti');
    $siapBukti = array_map(static fn($v) => preg_replace('/-[0-9a-f]+$/', '', (string) $v), $siapBukti);

    check(!in_array('TEST-CUTOFF-SEP', $siapBukti, true), 'Transaksi 30 September tidak tampil.');
    check(in_array('TEST-CUTOFF-OKT1', $siapBukti, true), 'Transaksi 1 Oktober tampil.');
    check(!in_array('TEST-CUTOFF-OKT2', $siapBukti, true), 'Transaksi yang sudah masuk pengajuan tidak tampil.');

    foreach ($siap as $row) {
        if (str_starts_with((string) ($row['nomor_bukti'] ?? ''), 'TEST-CUTOFF-')) {
            check(
                ($row['tanggal_efektif'] ?? '') >= PengajuanGu::SPJ_GU_MULAI,
                'Semua transaksi uji yang tampil ber tanggal >= 1 Oktober 2026.'
            );
        }
    }

    $siapDariSeptember = $model->getSiapSpj('2026-09-01');
    $buktiSeptember = array_column($siapDariSeptember, 'nomor_bukti');
    $buktiSeptember = array_map(static fn($v) => preg_replace('/-[0-9a-f]+$/', '', (string) $v), $buktiSeptember);
    check(!in_array('TEST-CUTOFF-SEP', $buktiSeptember, true), 'Filter dari September tetap mengecualikan transaksi sebelum 1 Oktober.');

    $siapDariTanggal5 = $model->getSiapSpj('2026-10-05');
    $buktiTanggal5 = array_column($siapDariTanggal5, 'nomor_bukti');
    $buktiTanggal5 = array_map(static fn($v) => preg_replace('/-[0-9a-f]+$/', '', (string) $v), $buktiTanggal5);
    check(!in_array('TEST-CUTOFF-OKT1', $buktiTanggal5, true), 'Filter dari 5 Oktober mengecualikan transaksi 1 Oktober.');

    $totalSetelah = $model->getTotalBelumSpj();
    check(
        abs(($totalSetelah - $totalSebelum) - 222000) < 0.001,
        'Total belum di-SPJ-kan hanya bertambah dari transaksi 1 Oktober (Rp222.000).'
    );

    echo PHP_EOL . "SEMUA TEST CUTOFF SPJ GU LULUS.\n";
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
