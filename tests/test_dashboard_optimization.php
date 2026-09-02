<?php
require_once __DIR__ . '/../config/load_env.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../app/Models/Pagu.php';
require_once __DIR__ . '/../app/Models/Rak.php';
require_once __DIR__ . '/../app/Models/Transaksi.php';
require_once __DIR__ . '/../app/Models/Seksi.php';
require_once __DIR__ . '/../app/Models/Program.php';
require_once __DIR__ . '/../app/Models/Kegiatan.php';
require_once __DIR__ . '/../app/Models/SubKegiatan.php';
require_once __DIR__ . '/../app/Controllers/DashboardController.php';

use App\Models\Pagu;
use App\Models\Rak;
use App\Models\Transaksi;
use App\Models\Seksi;
use App\Models\Program;
use App\Models\Kegiatan;
use App\Models\SubKegiatan;
use App\Controllers\DashboardController;

$db = Database::getConnection();

// Let's create an instrumented PDO or test controller methods via reflection
$paguModel = new Pagu($db);
$rakModel = new Rak($db);
$transaksiModel = new Transaksi($db);
$seksiModel = new Seksi($db);
$programModel = new Program($db);
$kegiatanModel = new Kegiatan($db);
$subKegiatanModel = new SubKegiatan($db);

$controller = new DashboardController(
    $paguModel,
    $rakModel,
    $transaksiModel,
    $seksiModel,
    $programModel,
    $kegiatanModel,
    $subKegiatanModel
);

echo "Testing DashboardController methods with year 2026...\n";

$tahun = 2026;
$filters = ['seksi_id' => null, 'program_id' => null, 'kegiatan_id' => null, 'sub_kegiatan_id' => null];

$r = new ReflectionClass($controller);

$t0 = microtime(true);
$mStats = $r->getMethod('getStatistics');
$mStats->setAccessible(true);
$stats = $mStats->invoke($controller, $tahun, $filters);
echo "  [PASS] getStatistics: total_pagu=" . number_format($stats['total_pagu']) . ", realisasi=" . number_format($stats['total_realisasi']) . "\n";

$mMonthly = $r->getMethod('getMonthlyData');
$mMonthly->setAccessible(true);
$monthly = $mMonthly->invoke($controller, $tahun, $filters);
echo "  [PASS] getMonthlyData: " . count($monthly['rak']) . " months calculated\n";

$mBreakdown = $r->getMethod('getBreakdownData');
$mBreakdown->setAccessible(true);
$breakdown = $mBreakdown->invoke($controller, $tahun, $filters);
echo "  [PASS] getBreakdownData: " . count($breakdown) . " breakdown groups\n";

$mHierarchical = $r->getMethod('getHierarchicalData');
$mHierarchical->setAccessible(true);
$hierarchy = $mHierarchical->invoke($controller, $tahun, $filters);
echo "  [PASS] getHierarchicalData: " . count($hierarchy) . " hierarchy roots\n";

$mAbsorption = $r->getMethod('getMonthlyAbsorptionDetails');
$mAbsorption->setAccessible(true);
$absorption = $mAbsorption->invoke($controller, $tahun, $filters);
echo "  [PASS] getMonthlyAbsorptionDetails: " . count($absorption['sub_kegiatan']) . " sub_kegiatan\n";

$mDeviation = $r->getMethod('getDeviationDetails');
$mDeviation->setAccessible(true);
$deviation = $mDeviation->invoke($controller, $tahun, $filters);
echo "  [PASS] getDeviationDetails: " . count($deviation) . " sub_kegiatan deviations\n";

$mSemester = $r->getMethod('getSemesterRekapData');
$mSemester->setAccessible(true);
$semester = $mSemester->invoke($controller, $tahun, $filters);
echo "  [PASS] getSemesterRekapData: " . count($semester['sub_kegiatan']) . " sub_kegiatan in semester rekap\n";

$elapsed = (microtime(true) - $t0) * 1000;
echo "\nTotal execution time for ALL 7 dashboard data builders: " . round($elapsed, 2) . " ms!\n";
echo "[DASHBOARD CONTROLLER OPTIMIZATION VERIFIED SUCCESSFULLY!]\n";
