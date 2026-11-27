<?php

declare(strict_types=1);

/**
 * Test Rekap STS TU: memastikan TU berstatus 'selesai' tetap muncul di rekap.
 * Regresi: dulu rekap hanya WHERE status='cair', jadi setelah semua TU
 * diubah ke 'selesai' rekap kosong dan file Excel tidak bisa diunduh.
 *
 * Data uji disemai sementara lalu dihapus di akhir.
 */

require_once __DIR__ . '/../config/load_env.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../vendor/autoload.php';

use App\Models\PengajuanTu;
use App\Services\TuStsExportService;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

function ok(bool $cond, string $msg): void
{
    if (!$cond) {
        throw new RuntimeException('GAGAL: ' . $msg);
    }
    echo "  [OK] {$msg}\n";
}

echo "=== TEST REKAP STS TU ===\n";

$db    = Database::getConnection();
$model = new PengajuanTu($db);

$tag      = 'TEST-STS-' . date('His');
$seksiId  = (int) $db->query("SELECT id FROM seksi ORDER BY id LIMIT 1")->fetchColumn();
$rek      = $db->query("
    SELECT r.id, r.sub_kegiatan_id
    FROM rekening r
    INNER JOIN sub_kegiatan sk ON sk.id = r.sub_kegiatan_id
    WHERE sk.seksi_id = {$seksiId}
    ORDER BY r.id LIMIT 1
")->fetch(PDO::FETCH_ASSOC);
if (!$rek) {
    echo "SKIP: tidak ada rekening untuk seksi {$seksiId}\n";
    exit(0);
}
$rekeningId   = (int) $rek['id'];
$subKegiatanId = (int) $rek['sub_kegiatan_id'];

$insertTu = $db->prepare("
    INSERT INTO pengajuan_tu
        (nomor_pengajuan, tanggal_pengajuan, seksi_id, rekening_id, tahun, bulan,
         kebutuhan, keperluan, status, nomor_sp2d, tanggal_cair, nominal_cair,
         batas_spj, nominal_digunakan, nominal_disetor)
    VALUES
        (:nomor, '2026-10-01', :seksi, :rekening, 2026, 10,
         1000000, :keperluan, :status, 'SP2D-TEST', '2026-10-02', 1000000,
         '2026-11-01', :digunakan, :disetor)
");
$insertRek = $db->prepare("
    INSERT INTO pengajuan_tu_rekening (pengajuan_tu_id, rekening_id, nominal)
    VALUES (:tu, :rekening, 1000000)
");
$insertTrx = $db->prepare("
    INSERT INTO transaksi
        (tanggal, tanggal_lunas_dibayar, seksi_id, rekening_id, uraian, nilai,
         status, sumber_dana, pengajuan_tu_id)
    VALUES ('2026-10-05', '2026-10-05', :seksi, :rekening, :uraian, :nilai,
            'diverifikasi', 'UP', :tu)
");

$createdTu  = [];
$createdTrx = [];

function seedTu(
    PDO $db,
    PDOStatement $insertTu,
    PDOStatement $insertRek,
    string $nomor,
    int $seksiId,
    int $rekeningId,
    string $status,
    ?int $digunakan,
    int $disetor,
    array &$createdTu
): int {
    $insertTu->execute([
        ':nomor'      => $nomor,
        ':seksi'      => $seksiId,
        ':rekening'   => $rekeningId,
        ':keperluan'  => 'TEST ' . $nomor,
        ':status'     => $status,
        ':digunakan'  => $digunakan,
        ':disetor'    => $disetor,
    ]);
    $id = (int) $db->lastInsertId();
    $createdTu[] = $id;
    $insertRek->execute([':tu' => $id, ':rekening' => $rekeningId]);
    return $id;
}

try {
    // A: selesai, snapshot terpakai 400rb, belum setor  → sisa 600rb
    seedTu($db, $insertTu, $insertRek, $tag . '-A', $seksiId, $rekeningId, 'selesai', 400000, 0, $createdTu);

    // B: cair, transaksi 250rb                         → sisa 750rb
    $tuB = seedTu($db, $insertTu, $insertRek, $tag . '-B', $seksiId, $rekeningId, 'cair', null, 0, $createdTu);
    $insertTrx->execute([
        ':seksi' => $seksiId, ':rekening' => $rekeningId,
        ':uraian' => 'TEST belanja TU B', ':nilai' => 250000, ':tu' => $tuB,
    ]);
    $createdTrx[] = (int) $db->lastInsertId();

    // C: selesai, terpakai 500rb, sudah setor 500rb        → sisa 0
    seedTu($db, $insertTu, $insertRek, $tag . '-C', $seksiId, $rekeningId, 'selesai', 500000, 500000, $createdTu);

    echo "\n1. Rekap memuat TU selesai (regresi utama)\n";
    $rekap = $model->getRekapSisaPerSubKegiatan();
    ok($rekap['rows'] !== [], 'rekap berisi baris walau semua TU sudah selesai');

    $row = null;
    foreach ($rekap['rows'] as $r) {
        if ((int) $r['sub_kegiatan_id'] === $subKegiatanId) { $row = $r; break; }
    }
    ok($row !== null, 'sub kegiatan uji muncul di rekap');

    echo "\n2. Angka rekap konsisten\n";
    printf(
        "  jumlah_tu=%d status=%s cair=%.0f terpakai=%.0f disetor=%.0f sisa=%.0f\n",
        $row['jumlah_tu'], $row['status'], $row['total_cair'],
        $row['total_terpakai'], $row['total_disetor'], $row['sisa_belum_disetor']
    );
    ok((float) $row['total_disetor'] >= 500000.0, 'setoran TU selesai (C) terhitung');
    ok((float) $row['total_terpakai'] >= 900000.0, 'TU selesai tanpa transaksi memakai snapshot nominal_digunakan');
    $expected = (float) $row['total_cair'] - (float) $row['total_terpakai'] - (float) $row['total_disetor'];
    ok(abs($expected - (float) $row['sisa_belum_disetor']) < 0.01, 'cair − terpakai − disetor = sisa belum disetor');
    ok(strpos((string) $row['status'], 'Selesai') !== false, 'kolom status menyebut Selesai');

    echo "\n3. Header Excel memiliki kolom baru\n";
    $service = new TuStsExportService();
    $sheet   = (new Spreadsheet())->getActiveSheet();
    $m = new ReflectionMethod($service, 'writeHeader');
    $m->setAccessible(true);
    $m->invoke($service, $sheet);
    ok($sheet->getCell('H6')->getValue() === 'DISETOR (Rp)', 'kolom H = DISETOR (Rp)');
    ok($sheet->getCell('I6')->getValue() === 'SISA BELUM DISETOR (Rp)', 'kolom I = SISA BELUM DISETOR (Rp)');
    ok($sheet->getCell('J6')->getValue() === 'STATUS', 'kolom J = STATUS');

    echo "\nSEMUA TEST REKAP STS TU LOLOS.\n";
} catch (Throwable $e) {
    echo "\n" . $e->getMessage() . "\n";
    exit(1);
} finally {
    foreach ($createdTrx as $id) {
        $db->prepare('DELETE FROM transaksi WHERE id = ?')->execute([$id]);
    }
    foreach ($createdTu as $id) {
        $db->prepare('DELETE FROM pengajuan_tu_rekening WHERE pengajuan_tu_id = ?')->execute([$id]);
        $db->prepare('DELETE FROM pengajuan_tu WHERE id = ?')->execute([$id]);
    }
    echo "Cleanup selesai.\n";
}
