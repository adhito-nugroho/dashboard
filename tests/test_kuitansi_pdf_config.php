<?php
require_once __DIR__ . '/../config/load_env.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../app/Services/KuitansiPdfService.php';

$service = new App\Services\KuitansiPdfService();
$data = $service->buildData([
    'id' => 1,
    'nilai' => 500000,
    'nomor_bukti' => '123.6.6/GU/001/IX/2026',
    'uraian' => 'Test Uraian'
]);

assert($data['no_bku'] === '', 'Nomor kuitansi / No. BKU harus kosong pada cetakan');
$koord = $service->getKoordinat();
assert($koord['terbilang_rp']['align'] === 'L', 'Terbilang Rp harus rata kiri (L)');

echo "✓ Kuitansi PDF config test passed!\n";
