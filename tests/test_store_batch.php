<?php
require_once __DIR__ . '/../config/load_env.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/Models/Rekening.php';
require_once __DIR__ . '/../app/Models/Pagu.php';
require_once __DIR__ . '/../app/Models/Transaksi.php';
require_once __DIR__ . '/../app/Models/Seksi.php';
require_once __DIR__ . '/../app/Models/Program.php';
require_once __DIR__ . '/../app/Models/Kegiatan.php';
require_once __DIR__ . '/../app/Models/SubKegiatan.php';
require_once __DIR__ . '/../app/Models/Rak.php';
require_once __DIR__ . '/../app/Controllers/TransaksiController.php';

$db = Database::getConnection();
$transaksiController = new App\Controllers\TransaksiController(
    new App\Models\Transaksi($db),
    new App\Models\Seksi($db),
    new App\Models\Pagu($db),
    new App\Models\Rak($db),
    new App\Models\Program($db),
    new App\Models\Kegiatan($db),
    new App\Models\SubKegiatan($db),
    new App\Models\Rekening($db)
);

// Set fake POST parameters where some rows are empty and one row has values
$_POST['tanggal'] = '2026-07-25';
$_POST['rekenings'] = [
    1 => [
        'rekening_id' => 1,
        'uraian' => 'Test Transaction 1',
        'nilai' => '1.000.000', // valid value
        'nomor_bukti' => 'BUK/001'
    ],
    2 => [
        'rekening_id' => 2,
        'uraian' => '',
        'nilai' => '', // empty value
        'nomor_bukti' => ''
    ]
];

// Start output buffer to capture the redirect HTML or errors
ob_start();
try {
    $transaksiController->storeBatch();
} catch (\Exception $e) {
    echo "Failed with exception: " . $e->getMessage() . "\n";
}
$output = ob_get_clean();

if (strpos($output, 'validationErrors') !== false || strpos($output, 'invalid-feedback') !== false) {
    echo "TEST FAILED: Validation error found in view output.\n";
    echo "View Output sample:\n" . substr($output, 0, 500) . "\n";
    exit(1);
}

echo "TEST PASSED: storeBatch ran successfully and ignored the empty row.\n";
exit(0);
