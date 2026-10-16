<?php
/**
 * Application Entry Point
 * 
 * Simple routing example for Program CRUD
 */

// Start session with secure cookie parameters
if (session_status() === PHP_SESSION_NONE) {
    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $isSecure,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// Increase max_input_vars if needed (for batch operations)
if (ini_get('max_input_vars') < 5000) {
    @ini_set('max_input_vars', '5000');
}

// Load environment variables
require_once __DIR__ . '/../config/load_env.php';

// Load helper functions
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/terbilang.php';

// Load database connection
require_once __DIR__ . '/../config/database.php';

// Load autoloader (if available)
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

// Catatan: kelas App\* (Models, Controllers, Services) dimuat otomatis via
// composer autoload (PSR-4 "App\\" -> "app/"). Jangan tambah require manual.

use App\Models\Program;
use App\Models\Kegiatan;
use App\Models\SubKegiatan;
use App\Models\Seksi;
use App\Models\Rekening;
use App\Models\Pagu;
use App\Models\PaguRiwayat;
use App\Models\Rak;
use App\Models\Transaksi;
use App\Models\KasBank;
use App\Controllers\ProgramController;
use App\Controllers\KegiatanController;
use App\Controllers\SubKegiatanController;
use App\Controllers\SeksiController;
use App\Controllers\PaguController;
use App\Controllers\RekeningController;
use App\Controllers\RakController;
use App\Controllers\TransaksiController;
use App\Controllers\DashboardController;
use App\Controllers\AuthController;
use App\Controllers\DashboardSeksiController;
use App\Controllers\SeksiTransaksiController;
use App\Controllers\ExcelController;
use App\Models\RincianBiaya;
use App\Controllers\SpjController;
use App\Controllers\KuitansiController;
use App\Controllers\KasBankController;
use App\Models\PengajuanGu;
use App\Controllers\PengajuanGuController;
use App\Models\TransferPerjadin;
use App\Controllers\TransferPerjadinController;

try {
    // Get database connection
    $db = Database::getConnection();

    // Initialize models and controllers
    $programModel = new Program($db);
    $kegiatanModel = new Kegiatan($db);
    $subKegiatanModel = new SubKegiatan($db);
    $seksiModel = new Seksi($db);
    $rekeningModel = new Rekening($db);
    $paguModel = new Pagu($db);
    $rakModel = new Rak($db);
    $transaksiModel = new Transaksi($db);
    $kasBankModel = new KasBank($db);
    $programController = new ProgramController($programModel);
    $kegiatanController = new KegiatanController($kegiatanModel, $programModel);
    $subKegiatanController = new SubKegiatanController($subKegiatanModel, $kegiatanModel, $seksiModel);
    $seksiController = new SeksiController($seksiModel);
    $paguRiwayatModel = null;
    try {
        $paguRiwayatModel = new PaguRiwayat($db);
    } catch (Throwable) {
        $paguRiwayatModel = null;
    }
    $paguController = new PaguController($paguModel, $programModel, $kegiatanModel, $subKegiatanModel, $rekeningModel, $paguRiwayatModel);
    $rekeningController = new RekeningController($rekeningModel, $programModel, $kegiatanModel, $subKegiatanModel);
    $rakController = new RakController($rakModel, $paguModel, $programModel, $kegiatanModel, $subKegiatanModel, $rekeningModel);
    $transaksiController = new TransaksiController($transaksiModel, $seksiModel, $paguModel, $rakModel, $programModel, $kegiatanModel, $subKegiatanModel, $rekeningModel);
    // Shared reporting service: satu sumber angka serapan untuk dashboard + export Excel.
    $serapanReportService = new \App\Services\SerapanReportService($db);
    $dashboardController = new DashboardController($paguModel, $rakModel, $transaksiModel, $seksiModel, $programModel, $kegiatanModel, $subKegiatanModel, $kasBankModel, $serapanReportService);
    $authController = new AuthController();
    $dashboardSeksiController = new DashboardSeksiController();
    $seksiTransaksiController = new SeksiTransaksiController();
    $excelController = new ExcelController($paguModel, $rakModel, $transaksiModel, $seksiModel, $db, $serapanReportService);
    $rincianBiayaModel = new RincianBiaya($db);
    $spjController = new SpjController($rincianBiayaModel);
    $kuitansiController = new KuitansiController($db);
    $kasBankController = new KasBankController($kasBankModel, $transaksiModel);
    $pengajuanGuModel = new PengajuanGu($db);
    $pengajuanGuController = new PengajuanGuController($pengajuanGuModel, $kasBankModel);
    $transferPerjadinModel = new TransferPerjadin($db);
    $transferPerjadinController = new TransferPerjadinController($transferPerjadinModel);

    // Simple routing
    $requestUri = $_SERVER['REQUEST_URI'];
    $requestMethod = $_SERVER['REQUEST_METHOD'];

    // Remove query string
    $path = parse_url($requestUri, PHP_URL_PATH);

    // Get script directory
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
    $scriptDir = dirname($scriptName);

    // Normalize script directory
    if ($scriptDir === '/' || $scriptDir === '\\' || $scriptDir === '.') {
        $scriptDir = '';
    }

    // Remove base path from request path
    // Handle different server configurations:
    // 1. If using router.php from root: /seksi should become /seksi
    // 2. If using public/index.php directly: /dashboard-anggaran/public/seksi should become /seksi
    // 3. If Laragon virtual host: /seksi should become /seksi (script dir is empty)

    // Detect and strip base path dynamically
    
    // 1. If script dir is present in path (e.g., /folder/public/index.php)
    if (!empty($scriptDir) && strpos($path, $scriptDir) === 0) {
        $path = substr($path, strlen($scriptDir));
    }
    // 2. If script dir ends in /public, check for parent dir (e.g., /folder)
    // This handles the case where URL is rewritten to hide public (e.g. /folder/foo -> /folder/public/index.php)
    elseif (substr($scriptDir, -7) === '/public') {
        $parentDir = substr($scriptDir, 0, -7);
        if (!empty($parentDir) && strpos($path, $parentDir) === 0) {
             $path = substr($path, strlen($parentDir));
        }
    }
    // 3. Fallback: if path starts with /public explicitly
    elseif (strpos($path, '/public') === 0) {
         $path = substr($path, 7);
    }
    // 4. If path contains /public (e.g. /dashboard/public/rak), use the part after /public
    elseif (preg_match('#/public(/.*)?$#', $path, $m)) {
        $path = (isset($m[1]) && $m[1] !== '') ? $m[1] : '/';
    }

    // Ensure path starts with /
    if (empty($path) || $path[0] !== '/') {
        $path = '/' . $path;
    }

    // Normalize path (remove trailing slash except for root)
    if ($path !== '/' && substr($path, -1) === '/') {
        $path = rtrim($path, '/');
    }

    // Debug: Temporary debug output (check error_log or uncomment to see in response)
    // Uncomment line below to see debug info in page source:
    // echo "<!-- DEBUG: Path='$path', ScriptDir='$scriptDir', URI='$requestUri', SCRIPT_NAME='$scriptName' -->";

    // Log path for debugging (can be removed later)
    error_log("Routing Debug: URI='$requestUri', Path='$path', ScriptDir='$scriptDir', SCRIPT_NAME='$scriptName'");

    // Define public routes (dashboard publik & export laporan dapat diakses tanpa login)
    $publicRoutes = [
        '/', '', '/dashboard', '/dashboard/', '/login', '/logout',
        '/export/laporan', '/export/serapan-bulanan', '/export/serapan-triwulan', '/export/sisa-semester',
        '/rak/export'
    ];
    
    // Check authentication for protected routes
    if (!in_array($path, $publicRoutes) && !isset($_SESSION['user_id'])) {
        header('Location: ' . base_url('login'));
        exit;
    }

    // Middleware: role seksi (tu/rlpm/tkuk) hanya boleh akses dashboard seksi & halaman transaksi seksi
    $isSeksi = isset($_SESSION['role']) && in_array($_SESSION['role'], ['rlpm', 'tkuk', 'tu', 'seksi'], true);
    if ($isSeksi) {
        $seksiAllowed = in_array($path, ['/logout', '/logout/'], true)
            || preg_match('#^/dashboard/(tu|rlpm|tkuk)$#', $path)
            || preg_match('#^/seksi/transaksi#', $path)
            || preg_match('#^/kuitansi/cetak/\d+$#', $path)
            || preg_match('#^/kuitansi/kalibrasi#', $path)
            || preg_match('#^/spj#', $path);
        if (!$seksiAllowed) {
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                http_response_code(403);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => false, 'message' => 'Akses ditolak untuk route ini.']);
                exit;
            }
            header('Location: ' . base_url('seksi/transaksi'));
            exit;
        }
    }

    // Route matching - Auth
    if ($path === '/login' || $path === '/login/') {
        if ($requestMethod === 'GET') {
            $authController->showLogin();
        } elseif ($requestMethod === 'POST') {
            $authController->login();
        }
        exit;
    } elseif ($path === '/logout' || $path === '/logout/') {
        $authController->logout();
        exit;
    }

    // ---- Route table: [HTTP method, path pattern, handler] ----
    // $routePattern is either an exact path ('/program') or a regex
    // ('#^/program/edit/(\d+)$#'). Handler receives preg matches ([] if exact).
    // First match wins; method 'ANY' matches all request methods.
    // NOTE: $path is already normalized above (no trailing slash except root,
    // always leading '/'), so canonical forms are sufficient here.
    $routes = [
        // Dashboard
        ['ANY', '/', fn($m) => $dashboardController->index()],
        ['ANY', '/dashboard', fn($m) => $dashboardController->index()],
        ['ANY', '/dashboard/tu', fn($m) => $dashboardSeksiController->showTU()],
        ['ANY', '/dashboard/rlpm', fn($m) => $dashboardSeksiController->showRLPM()],
        ['ANY', '/dashboard/tkuk', fn($m) => $dashboardSeksiController->showTKUK()],

        // Excel Export
        ['ANY', '/export/laporan', fn($m) => $excelController->exportLaporan()],
        ['ANY', '/export/serapan-bulanan', fn($m) => $excelController->exportSerapanBulanan()],
        ['ANY', '/export/serapan-triwulan', fn($m) => $excelController->exportSerapanTriwulan()],
        ['ANY', '/export/sisa-semester', fn($m) => $excelController->exportSisaSemester()],

        // Program
        ['ANY', '/program', fn($m) => $programController->index()],
        ['GET', '/program/create', fn($m) => $programController->create()],
        ['POST', '/program/create', fn($m) => $programController->store()],
        ['POST', '/program/store', fn($m) => $programController->store()],
        ['GET', '#^/program/edit/(\d+)$#', fn($m) => $programController->edit((int) $m[1])],
        ['POST', '#^/program/edit/(\d+)$#', fn($m) => $programController->update((int) $m[1])],
        ['POST', '#^/program/update/(\d+)$#', fn($m) => $programController->update((int) $m[1])],
        ['ANY', '#^/program/delete/(\d+)$#', fn($m) => $programController->delete((int) $m[1])],

        // Kegiatan
        ['ANY', '/kegiatan', fn($m) => $kegiatanController->index()],
        ['GET', '/kegiatan/create', fn($m) => $kegiatanController->create()],
        ['POST', '/kegiatan/create', fn($m) => $kegiatanController->store()],
        ['POST', '/kegiatan/store', fn($m) => $kegiatanController->store()],
        ['GET', '#^/kegiatan/edit/(\d+)$#', fn($m) => $kegiatanController->edit((int) $m[1])],
        ['POST', '#^/kegiatan/edit/(\d+)$#', fn($m) => $kegiatanController->update((int) $m[1])],
        ['POST', '#^/kegiatan/update/(\d+)$#', fn($m) => $kegiatanController->update((int) $m[1])],
        ['ANY', '#^/kegiatan/delete/(\d+)$#', fn($m) => $kegiatanController->delete((int) $m[1])],

        // Sub Kegiatan
        ['ANY', '/sub-kegiatan', fn($m) => $subKegiatanController->index()],
        ['GET', '/sub-kegiatan/create', fn($m) => $subKegiatanController->create()],
        ['POST', '/sub-kegiatan/create', fn($m) => $subKegiatanController->store()],
        ['POST', '/sub-kegiatan/store', fn($m) => $subKegiatanController->store()],
        ['GET', '#^/sub-kegiatan/edit/(\d+)$#', fn($m) => $subKegiatanController->edit((int) $m[1])],
        ['POST', '#^/sub-kegiatan/edit/(\d+)$#', fn($m) => $subKegiatanController->update((int) $m[1])],
        ['POST', '#^/sub-kegiatan/update/(\d+)$#', fn($m) => $subKegiatanController->update((int) $m[1])],
        ['ANY', '#^/sub-kegiatan/delete/(\d+)$#', fn($m) => $subKegiatanController->delete((int) $m[1])],

        // Seksi
        ['ANY', '/seksi', fn($m) => $seksiController->index()],
        ['GET', '/seksi/create', fn($m) => $seksiController->create()],
        ['POST', '/seksi/create', fn($m) => $seksiController->store()],
        ['POST', '/seksi/store', fn($m) => $seksiController->store()],
        ['GET', '#^/seksi/edit/(\d+)$#', fn($m) => $seksiController->edit((int) $m[1])],
        ['POST', '#^/seksi/edit/(\d+)$#', fn($m) => $seksiController->update((int) $m[1])],
        ['POST', '#^/seksi/update/(\d+)$#', fn($m) => $seksiController->update((int) $m[1])],
        ['ANY', '#^/seksi/delete/(\d+)$#', fn($m) => $seksiController->delete((int) $m[1])],

        // Pagu
        ['ANY', '/pagu', fn($m) => $paguController->index()],
        ['GET', '/pagu/create', fn($m) => $paguController->create()],
        ['POST', '/pagu/create', fn($m) => $paguController->store()],
        ['POST', '/pagu/store', fn($m) => $paguController->store()],
        ['POST', '/pagu/store-batch', fn($m) => $paguController->storeBatch()],
        ['ANY', '/pagu/laporan', fn($m) => $paguController->laporan()],
        ['ANY', '#^/pagu/riwayat/(\d+)$#', fn($m) => $paguController->riwayat((int) $m[1])],
        ['GET', '#^/pagu/edit/(\d+)$#', fn($m) => $paguController->edit((int) $m[1])],
        ['POST', '#^/pagu/edit/(\d+)$#', fn($m) => $paguController->update((int) $m[1])],
        ['POST', '#^/pagu/update/(\d+)$#', fn($m) => $paguController->update((int) $m[1])],
        ['ANY', '#^/pagu/delete/(\d+)$#', fn($m) => $paguController->delete((int) $m[1])],
        ['GET', '/pagu/get-kegiatans', fn($m) => $paguController->getKegiatansByProgram()],
        ['GET', '/pagu/get-sub-kegiatans', fn($m) => $paguController->getSubKegiatansByKegiatan()],
        ['GET', '/pagu/get-rekenings', fn($m) => $paguController->getRekeningsBySubKegiatan()],

        // Rekening
        ['ANY', '/rekening', fn($m) => $rekeningController->index()],
        ['GET', '/rekening/create', fn($m) => $rekeningController->create()],
        ['POST', '/rekening/create', fn($m) => $rekeningController->store()],
        ['POST', '/rekening/store', fn($m) => $rekeningController->store()],
        ['POST', '/rekening/store-batch', fn($m) => $rekeningController->storeBatch()],
        ['GET', '#^/rekening/edit/(\d+)$#', fn($m) => $rekeningController->edit((int) $m[1])],
        ['POST', '#^/rekening/edit/(\d+)$#', fn($m) => $rekeningController->update((int) $m[1])],
        ['POST', '#^/rekening/update/(\d+)$#', fn($m) => $rekeningController->update((int) $m[1])],
        ['ANY', '#^/rekening/delete/(\d+)$#', fn($m) => $rekeningController->delete((int) $m[1])],

        // RAK
        ['GET', '/rak/export', fn($m) => $rakController->export()],
        ['ANY', '/rak/rekap', fn($m) => $rakController->rekap()],
        ['ANY', '/rak', fn($m) => $rakController->index()],
        ['GET', '/rak/create', fn($m) => $rakController->create()],
        ['POST', '/rak/create', fn($m) => $rakController->store()],
        ['POST', '/rak/store', fn($m) => $rakController->store()],
        ['GET', '#^/rak/edit/(\d+)/(\d+)$#', fn($m) => $rakController->edit((int) $m[1], (int) $m[2])],
        ['POST', '#^/rak/edit/(\d+)/(\d+)$#', fn($m) => $rakController->update((int) $m[1], (int) $m[2])],
        ['POST', '#^/rak/update/(\d+)/(\d+)$#', fn($m) => $rakController->update((int) $m[1], (int) $m[2])],
        ['ANY', '#^/rak/delete/(\d+)/(\d+)$#', fn($m) => $rakController->delete((int) $m[1], (int) $m[2])],
        ['GET', '/rak/get-rekening-info', fn($m) => $rakController->getRekeningInfo()],

        // Transaksi
        ['ANY', '/transaksi', fn($m) => $transaksiController->index()],
        ['GET', '/transaksi/create', fn($m) => $transaksiController->create()],
        ['POST', '/transaksi/create', fn($m) => $transaksiController->store()],
        ['POST', '/transaksi/store', fn($m) => $transaksiController->store()],
        ['POST', '/transaksi/store-batch', fn($m) => $transaksiController->storeBatch()],
        ['GET', '#^/transaksi/edit/(\d+)$#', fn($m) => $transaksiController->edit((int) $m[1])],
        ['POST', '#^/transaksi/update/(\d+)$#', fn($m) => $transaksiController->update((int) $m[1])],
        ['ANY', '#^/transaksi/delete/(\d+)$#', fn($m) => $transaksiController->delete((int) $m[1])],
        ['POST', '/transaksi/delete-batch', fn($m) => $transaksiController->deleteBatch()],
        ['POST', '#^/transaksi/verifikasi/(\d+)$#', fn($m) => $transaksiController->verifikasi((int) $m[1])],
        ['POST', '/transaksi/verifikasi-batch', fn($m) => $transaksiController->verifikasiBatch()],
        ['POST', '#^/transaksi/tolak/(\d+)$#', fn($m) => $transaksiController->tolak((int) $m[1])],
        ['POST', '#^/transaksi/batal-verifikasi/(\d+)$#', fn($m) => $transaksiController->batalVerifikasi((int) $m[1])],
        ['POST', '#^/transaksi/update-tanggal-lunas/(\d+)$#', fn($m) => $transaksiController->updateTanggalLunas((int) $m[1])],
        ['GET', '/transaksi/get-remaining-pagu', fn($m) => $transaksiController->getRemainingPagu()],
        ['GET', '/transaksi/generate-no-bukti', fn($m) => $transaksiController->generateNomorBukti()],
        ['GET', '/transaksi/get-rekenings-with-budget', fn($m) => $transaksiController->getRekeningsWithBudget()],
        ['GET', '/transaksi/cari-klasifikasi', fn($m) => $transaksiController->cariKlasifikasi()],
        ['GET', '/transaksi/bku-cdk', fn($m) => $transaksiController->downloadBkuCdk()],
        ['ANY', '#^/transaksi/show/(\d+)$#', fn($m) => $transaksiController->show((int) $m[1])],

        // SPJ Rincian Biaya Perjalanan Dinas
        ['ANY', '/spj', fn($m) => $spjController->index()],
        ['ANY', '#^/spj/detail/(\d+)$#', fn($m) => $spjController->detail((int) $m[1])],
        ['ANY', '#^/spj/create/(\d+)/(.+)$#', fn($m) => $spjController->create((int) $m[1], urldecode($m[2]))],
        ['POST', '/spj/store', fn($m) => $spjController->store()],
        ['ANY', '#^/spj/edit/(\d+)$#', fn($m) => $spjController->edit((int) $m[1])],
        ['POST', '#^/spj/update/(\d+)$#', fn($m) => $spjController->update((int) $m[1])],
        ['POST', '#^/spj/delete/(\d+)$#', fn($m) => $spjController->delete((int) $m[1])],
        ['ANY', '#^/spj/unduh/(\d+)$#', fn($m) => $spjController->download((int) $m[1])],
        ['ANY', '#^/spj/spd/(\d+)/(.+)$#', fn($m) => $spjController->unduhSpd((int) $m[1], urldecode($m[2]))],
        ['ANY', '#^/spj/spd-transaksi/(\d+)$#', fn($m) => $spjController->unduhSpdTransaksi((int) $m[1])],

        // Kuitansi NCR 215x165mm (kalibrasi admin only, cetak admin+seksi)
        ['ANY', '/kuitansi/kalibrasi', fn($m) => $kuitansiController->kalibrasi()],
        ['POST', '/kuitansi/kalibrasi/simpan', fn($m) => $kuitansiController->simpanElemen()],
        ['POST', '/kuitansi/kalibrasi/uji', fn($m) => $kuitansiController->cetakUji()],
        ['POST', '/kuitansi/kalibrasi/upload', fn($m) => $kuitansiController->uploadReferensi()],
        ['POST', '/kuitansi/kalibrasi/printer/tambah', fn($m) => $kuitansiController->tambahPrinter()],
        ['POST', '/kuitansi/kalibrasi/printer/default', fn($m) => $kuitansiController->setDefaultPrinter()],
        ['POST', '/kuitansi/kalibrasi/printer/hapus', fn($m) => $kuitansiController->hapusPrinter()],
        ['ANY', '#^/kuitansi/cetak/(\d+)$#', fn($m) => $kuitansiController->cetak((int) $m[1])],

        // Input Transaksi Seksi (role seksi)
        ['ANY', '/seksi/transaksi', fn($m) => $seksiTransaksiController->index()],
        ['ANY', '/seksi/transaksi/create', fn($m) => $seksiTransaksiController->create()],
        ['POST', '/seksi/transaksi/store', fn($m) => $seksiTransaksiController->store()],
        ['ANY', '#^/seksi/transaksi/edit/(\d+)$#', fn($m) => $seksiTransaksiController->edit((int) $m[1])],
        ['POST', '#^/seksi/transaksi/update/(\d+)$#', fn($m) => $seksiTransaksiController->update((int) $m[1])],
        ['POST', '#^/seksi/transaksi/delete/(\d+)$#', fn($m) => $seksiTransaksiController->delete((int) $m[1])],
        ['GET', '/seksi/transaksi/kegiatans', fn($m) => $seksiTransaksiController->getKegiatans()],
        ['GET', '/seksi/transaksi/subkegiatans', fn($m) => $seksiTransaksiController->getSubKegiatans()],
        ['GET', '/seksi/transaksi/rekenings', fn($m) => $seksiTransaksiController->getRekenings()],
        ['GET', '/seksi/transaksi/sisa-pagu', fn($m) => $seksiTransaksiController->getSisaPagu()],
        ['GET', '/seksi/transaksi/generate-no-bukti', fn($m) => $seksiTransaksiController->generateNomorBukti()],
        ['GET', '/seksi/transaksi/search-st', fn($m) => $seksiTransaksiController->searchSuratTugas()],
        ['GET', '/seksi/transaksi/cari-klasifikasi', fn($m) => $seksiTransaksiController->cariKlasifikasi()],
        ['GET', '/seksi/transaksi/pegawai-st', fn($m) => $seksiTransaksiController->getPegawaiSuratTugas()],
        ['GET', '/seksi/transaksi/bku', fn($m) => $seksiTransaksiController->downloadBku()],
        ['GET', '/seksi/transaksi/unduh-rincian-biaya', fn($m) => $seksiTransaksiController->downloadRincianBiaya()],

        // Kas & Bank (UP/GU)
        ['ANY', '/kas-bank', fn($m) => $kasBankController->index()],
        ['POST', '/kas-bank/store', fn($m) => $kasBankController->store()],
        ['POST', '#^/kas-bank/cairkan/(\d+)$#', fn($m) => $kasBankController->cairkan((int) $m[1])],
        ['POST', '#^/kas-bank/update/(\d+)$#', fn($m) => $kasBankController->update((int) $m[1])],
        ['POST', '#^/kas-bank/delete/(\d+)$#', fn($m) => $kasBankController->delete((int) $m[1])],

        // Pengajuan SPJ ke GU
        ['ANY', '/pengajuan-gu', fn($m) => $pengajuanGuController->index()],
        ['POST', '/pengajuan-gu/store', fn($m) => $pengajuanGuController->store()],
        ['POST', '#^/pengajuan-gu/cairkan/(\d+)$#', fn($m) => $pengajuanGuController->cairkan((int) $m[1])],
        ['POST', '#^/pengajuan-gu/batalkan/(\d+)$#', fn($m) => $pengajuanGuController->batalkan((int) $m[1])],
        ['POST', '#^/pengajuan-gu/lepas/(\d+)/(\d+)$#', fn($m) => $pengajuanGuController->lepas((int) $m[1], (int) $m[2])],

        // Transfer Perjalanan Dinas (admin only)
        ['ANY', '/transfer-perjadin', fn($m) => $transferPerjadinController->index()],
        ['ANY', '#^/transfer-perjadin/atur/(\d+)$#', fn($m) => $transferPerjadinController->atur((int) $m[1])],
        ['POST', '/transfer-perjadin/save', fn($m) => $transferPerjadinController->save()],
        ['GET', '/transfer-perjadin/export', fn($m) => $transferPerjadinController->export()],
    ];

    // ---- Dispatcher: first match wins ----
    $routeMatched = false;
    foreach ($routes as $route) {
        [$routeMethod, $routePattern, $routeHandler] = $route;
        if ($routeMethod !== 'ANY' && $routeMethod !== $requestMethod) {
            continue;
        }
        if ($routePattern[0] === '#') {
            if (!preg_match($routePattern, $path, $routeMatches)) {
                continue;
            }
            $routeHandler($routeMatches);
        } else {
            if ($routePattern !== $path) {
                continue;
            }
            $routeHandler([]);
        }
        $routeMatched = true;
        break;
    }

    if (!$routeMatched) {
        // 404 Not Found
        http_response_code(404);
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>404 - Not Found</title>';
        echo '<style>body{font-family:Arial,sans-serif;max-width:800px;margin:50px auto;padding:20px;}';
        echo 'h1{color:#d32f2f;}code{background:#f5f5f5;padding:2px 6px;border-radius:3px;}</style></head><body>';
        echo '<h1>404 - Page Not Found</h1>';
        echo '<p>The requested URL <code>' . htmlspecialchars($requestUri) . '</code> was not found on this server.</p>';
        echo '<p><strong>Debug Info:</strong></p>';
        echo '<ul>';
        echo '<li>Path: <code>' . htmlspecialchars($path) . '</code></li>';
        echo '<li>Script Dir: <code>' . htmlspecialchars($scriptDir) . '</code></li>';
        echo '<li>Script Name: <code>' . htmlspecialchars($scriptName) . '</code></li>';
        echo '</ul>';
        echo '<p><strong>Available Routes:</strong></p>';
        echo '<ul>';
        echo '<li><a href="' . base_url() . '">Dashboard</a></li>';
        echo '<li><a href="' . base_url('program') . '">Program</a></li>';
        echo '<li><a href="' . base_url('kegiatan') . '">Kegiatan</a></li>';
        echo '<li><a href="' . base_url('sub-kegiatan') . '">Sub Kegiatan</a></li>';
        echo '<li><a href="' . base_url('seksi') . '">Seksi</a></li>';
        echo '<li><a href="' . base_url('pagu') . '">Pagu</a></li>';
        echo '<li><a href="' . base_url('rak') . '">RAK</a></li>';
        echo '<li><a href="' . base_url('rekening') . '">Rekening</a></li>';
        echo '<li><a href="' . base_url('transaksi') . '">Transaksi</a></li>';
        echo '</ul>';
        echo '<p><strong>Note:</strong> Make sure you are running the server with <code>php -S localhost:8000 router.php</code> from the project root directory.</p>';
        echo '</body></html>';
    }

} catch (Exception $e) {
    http_response_code(500);
    echo '<h1>500 - Internal Server Error</h1>';
    echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
    error_log('Application error: ' . $e->getMessage());
}
