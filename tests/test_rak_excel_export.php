<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/load_env.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../vendor/autoload.php';

require_once __DIR__ . '/../app/Models/Rak.php';
require_once __DIR__ . '/../app/Models/Pagu.php';
require_once __DIR__ . '/../app/Models/Program.php';
require_once __DIR__ . '/../app/Models/Kegiatan.php';
require_once __DIR__ . '/../app/Models/SubKegiatan.php';
require_once __DIR__ . '/../app/Models/Rekening.php';
require_once __DIR__ . '/../app/Controllers/RakController.php';

use App\Models\Rak;
use App\Models\Pagu;
use App\Models\Program;
use App\Models\Kegiatan;
use App\Models\SubKegiatan;
use App\Models\Rekening;
use App\Controllers\RakController;

$db = Database::getConnection();
$rakModel = new Rak($db);
$paguModel = new Pagu($db);
$programModel = new Program($db);
$kegiatanModel = new Kegiatan($db);
$subKegiatanModel = new SubKegiatan($db);
$rekeningModel = new Rekening($db);

$controller = new RakController(
    $rakModel,
    $paguModel,
    $programModel,
    $kegiatanModel,
    $subKegiatanModel,
    $rekeningModel
);

// Verifikasi method export ada di RakController
$reflection = new ReflectionClass($controller);
assert($reflection->hasMethod('export'), 'RakController harus memiliki method export()');

// Verifikasi method buildSpreadsheet ada untuk testing unit/isolasi output
assert($reflection->hasMethod('buildSpreadsheet'), 'RakController harus memiliki method buildSpreadsheet()');

$method = $reflection->getMethod('buildSpreadsheet');
$spreadsheet = $method->invoke($controller, null, null, null);

assert($spreadsheet instanceof \PhpOffice\PhpSpreadsheet\Spreadsheet, 'Output buildSpreadsheet harus instance dari Spreadsheet');

$sheet = $spreadsheet->getActiveSheet();
assert($sheet->getCell('A1')->getValue() === 'RENCANA ANGGARAN KAS (RAK)', 'Judul cell A1 harus RENCANA ANGGARAN KAS (RAK)');
assert($sheet->getCell('A5')->getValue() === 'No', 'Header A5 harus No');
assert($sheet->getCell('B5')->getValue() === 'Kode Rekening', 'Header B5 harus Kode Rekening');
assert($sheet->getCell('H5')->getValue() === 'Jan', 'Header H5 harus Jan');
assert($sheet->getCell('T5')->getValue() === 'Total (Rp)', 'Header T5 harus Total (Rp)');

echo "✓ Test RAK Excel Export berhasil!\n";
