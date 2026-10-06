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
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;

$db = Database::getConnection();
$controller = new RakController(
    new Rak($db),
    new Pagu($db),
    new Program($db),
    new Kegiatan($db),
    new SubKegiatan($db),
    new Rekening($db)
);

// 1. Test build spreadsheet dengan filter tahun 2026
$ss2026 = $controller->buildSpreadsheet(2026, null, null);
assert($ss2026->getSheetCount() === 1, 'Harus ada 1 sheet');

// 2. Simpan ke temporary file untuk memastikan writer Xlsx bekerja tanpa error
$tmpFile = sys_get_temp_dir() . '/test_rak_export_' . time() . '.xlsx';
$writer = new Xlsx($ss2026);
$writer->save($tmpFile);

assert(file_exists($tmpFile), 'File temporary excel harus berhasil disimpan');
assert(filesize($tmpFile) > 1000, 'Ukuran file excel harus valid (> 1KB)');

// 3. Baca kembali menggunakan IOFactory untuk memverifikasi integritas file spreadsheet
$reader = IOFactory::createReader('Xlsx');
$loadedSpreadsheet = $reader->load($tmpFile);
$loadedSheet = $loadedSpreadsheet->getActiveSheet();

assert($loadedSheet->getTitle() === 'RAK', 'Title sheet harus RAK');
assert($loadedSheet->getCell('A1')->getValue() === 'RENCANA ANGGARAN KAS (RAK)', 'Judul A1 harus cocok');
assert(str_contains((string)$loadedSheet->getCell('A2')->getValue(), 'Tahun: 2026'), 'A2 harus memuat Tahun: 2026');

@unlink($tmpFile);

echo "✓ Test E2E RAK Export XLSX berhasil menghasilkan file valid!\n";
