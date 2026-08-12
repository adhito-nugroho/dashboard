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

// Set fake GET parameters
$_GET['sub_kegiatan_id'] = 1; // Assuming Sub Kegiatan 1 exists
$_GET['tahun'] = 2026;

// We expect this file to be run via PHP CLI. We can buffer output of the controller method.
ob_start();
try {
    $transaksiController->getRekeningsWithBudget();
} catch (\Exception $e) {
    echo "Failed with exception: " . $e->getMessage() . "\n";
}
$output = ob_get_clean();

$data = json_decode($output, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    echo "TEST FAILED: Invalid JSON output. Raw output: \n" . $output . "\n";
    exit(1);
}

echo "TEST PASSED: Found " . count($data) . " accounts. Sample data:\n";
print_r(array_slice($data, 0, 1));
exit(0);
