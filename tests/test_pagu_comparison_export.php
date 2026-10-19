<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/load_env.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../vendor/autoload.php';

use App\Models\Pagu;
use App\Models\PaguRiwayat;
use App\Services\PaguComparisonExportService;

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException('GAGAL: ' . $message);
    }
    echo 'OK: ' . $message . PHP_EOL;
}

echo "=== TEST EXPORT APBD MURNI VS PAPBD ===\n";

$db = Database::getConnection();
$db->beginTransaction();

try {
    $tahun = 2099;
    $rekening = $db->query("
        SELECT r.id, r.kode_rekening, r.nama_rekening
        FROM rekening r
        INNER JOIN sub_kegiatan sk ON sk.id = r.sub_kegiatan_id
        INNER JOIN kegiatan k ON k.id = sk.kegiatan_id
        INNER JOIN program p ON p.id = k.program_id
        ORDER BY r.id
        LIMIT 1
    ")->fetch(PDO::FETCH_ASSOC);

    if (!$rekening) {
        throw new RuntimeException('Butuh minimal satu rekening untuk pengujian.');
    }

    $rekeningId = (int) $rekening['id'];
    $before = 1250000.0;
    $after = 1750000.0;

    $insertPagu = $db->prepare("
        INSERT INTO pagu (rekening_id, tahun, nilai_pagu)
        VALUES (:rekening_id, :tahun, :nilai_pagu)
    ");
    $insertPagu->execute([
        ':rekening_id' => $rekeningId,
        ':tahun' => $tahun,
        ':nilai_pagu' => $after,
    ]);
    $paguId = (int) $db->lastInsertId();

    $insertRiwayat = $db->prepare("
        INSERT INTO pagu_riwayat
            (pagu_id, rekening_id, tahun, nilai_sebelum, nilai_sesudah, selisih, jenis, keterangan, created_by)
        VALUES
            (:pagu_id, :rekening_id, :tahun, :nilai_sebelum, :nilai_sesudah, :selisih, 'PAPBD', :keterangan, NULL)
    ");
    $insertRiwayat->execute([
        ':pagu_id' => $paguId,
        ':rekening_id' => $rekeningId,
        ':tahun' => $tahun,
        ':nilai_sebelum' => $before,
        ':nilai_sesudah' => $after,
        ':selisih' => $after - $before,
        ':keterangan' => 'Pengujian export perbandingan',
    ]);

    $service = new PaguComparisonExportService(new Pagu($db), new PaguRiwayat($db));
    $report = $service->buildReport($tahun);

    $rekeningRow = null;
    foreach ($report['rows'] as $row) {
        if (($row['type'] ?? '') === 'rekening'
            && (string) ($row['kode'] ?? '') === (string) $rekening['kode_rekening']) {
            $rekeningRow = $row;
            break;
        }
    }

    check($rekeningRow !== null, 'baris rekening ditemukan');
    check(abs((float) $rekeningRow['before'] - $before) < 0.001, 'nilai sebelum = APBD murni');
    check(abs((float) $rekeningRow['after'] - $after) < 0.001, 'nilai sesudah = PAPBD');
    check(abs((float) $rekeningRow['difference'] - ($after - $before)) < 0.001, 'selisih rekening benar');
    check(abs((float) $report['totals']['before'] - $before) < 0.001, 'total sebelum benar');
    check(abs((float) $report['totals']['after'] - $after) < 0.001, 'total sesudah benar');
    check((int) $report['counts']['bertambah'] === 1, 'ringkasan jumlah rekening bertambah');

    $spreadsheet = $service->createSpreadsheet($report);
    $sheet = $spreadsheet->getActiveSheet();

    $sheetRow = null;
    foreach ($sheet->toArray(null, true, true, false) as $index => $cells) {
        if ((string) ($cells[0] ?? '') === (string) $rekening['kode_rekening']
            && trim((string) ($cells[1] ?? '')) === (string) $rekening['nama_rekening']) {
            $sheetRow = $index + 1;
            break;
        }
    }

    check($sheetRow !== null, 'baris rekening ada di worksheet');
    check(abs((float) $sheet->getCell('C' . $sheetRow)->getValue() - $before) < 0.001, 'sel Excel APBD murni benar');
    check(abs((float) $sheet->getCell('D' . $sheetRow)->getValue() - $after) < 0.001, 'sel Excel PAPBD benar');
    check(abs((float) $sheet->getCell('E' . $sheetRow)->getValue() - ($after - $before)) < 0.001, 'sel Excel selisih benar');
    check((string) $sheet->getCell('F' . $sheetRow)->getValue() === 'Bertambah', 'keterangan bertambah benar');

    $db->rollBack();
    echo "=== SEMUA TEST LULUS ===\n";
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}
