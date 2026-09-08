<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/load_env.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/database.php';

$db = Database::getConnection();

// Test sorting comparator
$testRows = [
    ['nomor_bukti' => '123.6.6/GU/10/IX/2026', 'tanggal' => '2026-09-01', 'tanggal_lunas_dibayar' => '2026-09-10'],
    ['nomor_bukti' => '123.6.6/GU/2/IX/2026',  'tanggal' => '2026-09-05', 'tanggal_lunas_dibayar' => '2026-09-02'],
    ['nomor_bukti' => '123.6.6/GU/1/IX/2026',  'tanggal' => '2026-09-08', 'tanggal_lunas_dibayar' => '2026-09-01'],
    ['nomor_bukti' => '123.6.6/GU/DRAFT-1/IX/2026', 'tanggal' => '2026-09-09', 'tanggal_lunas_dibayar' => null],
];

usort($testRows, function ($a, $b) {
    $nbA = (string) ($a['nomor_bukti'] ?? '');
    $nbB = (string) ($b['nomor_bukti'] ?? '');

    $isResmiA = preg_match('#^123\.6\.6/GU/(\d+)/#i', $nbA, $mA);
    $isResmiB = preg_match('#^123\.6\.6/GU/(\d+)/#i', $nbB, $mB);

    if ($isResmiA && $isResmiB) {
        $numA = (int) $mA[1];
        $numB = (int) $mB[1];
        if ($numA !== $numB) {
            return $numA <=> $numB;
        }
        return strnatcasecmp($nbA, $nbB);
    }
    if ($isResmiA) return -1;
    if ($isResmiB) return 1;

    return strnatcasecmp($nbA, $nbB);
});

assert($testRows[0]['nomor_bukti'] === '123.6.6/GU/1/IX/2026', 'Urutan pertama harus No 1');
assert($testRows[1]['nomor_bukti'] === '123.6.6/GU/2/IX/2026', 'Urutan kedua harus No 2');
assert($testRows[2]['nomor_bukti'] === '123.6.6/GU/10/IX/2026', 'Urutan ketiga harus No 10');
assert($testRows[3]['nomor_bukti'] === '123.6.6/GU/DRAFT-1/IX/2026', 'Draft harus di urutan akhir');

// Test tanggal display
foreach ($testRows as $t) {
    $rawTgl = !empty($t['tanggal_lunas_dibayar']) ? $t['tanggal_lunas_dibayar'] : $t['tanggal'];
    $tglDisplay = date('d/m/Y', strtotime($rawTgl));
    if ($t['nomor_bukti'] === '123.6.6/GU/1/IX/2026') {
        assert($tglDisplay === '01/09/2026', 'Tanggal harus tanggal verifikasi 01/09/2026');
    }
}

echo "✓ Test BKU Excel sorting & tanggal verifikasi lolos!\n";
