<?php
/**
 * Application Entry Point
 * 
 * Simple routing example for Program CRUD
 */

// Start session
if (session_status() === PHP_SESSION_NONE) {
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

// Load database connection
require_once __DIR__ . '/../config/database.php';

// Load autoloader (if available)
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

// Load models and controllers
require_once __DIR__ . '/../app/Models/Program.php';
require_once __DIR__ . '/../app/Models/Kegiatan.php';
require_once __DIR__ . '/../app/Models/SubKegiatan.php';
require_once __DIR__ . '/../app/Models/Seksi.php';
require_once __DIR__ . '/../app/Models/Rekening.php';
require_once __DIR__ . '/../app/Models/Pagu.php';
require_once __DIR__ . '/../app/Models/Rak.php';
require_once __DIR__ . '/../app/Models/Transaksi.php';
require_once __DIR__ . '/../app/Controllers/ProgramController.php';
require_once __DIR__ . '/../app/Controllers/KegiatanController.php';
require_once __DIR__ . '/../app/Controllers/SubKegiatanController.php';
require_once __DIR__ . '/../app/Controllers/SeksiController.php';
require_once __DIR__ . '/../app/Controllers/PaguController.php';
require_once __DIR__ . '/../app/Controllers/RekeningController.php';
require_once __DIR__ . '/../app/Controllers/RakController.php';
require_once __DIR__ . '/../app/Controllers/TransaksiController.php';
require_once __DIR__ . '/../app/Controllers/DashboardController.php';
require_once __DIR__ . '/../app/Controllers/AuthController.php';
require_once __DIR__ . '/../app/Controllers/DashboardSeksiController.php';
require_once __DIR__ . '/../app/Controllers/SeksiTransaksiController.php';
require_once __DIR__ . '/../app/Controllers/ExcelController.php';

use App\Models\Program;
use App\Models\Kegiatan;
use App\Models\SubKegiatan;
use App\Models\Seksi;
use App\Models\Rekening;
use App\Models\Pagu;
use App\Models\Rak;
use App\Models\Transaksi;
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
    $programController = new ProgramController($programModel);
    $kegiatanController = new KegiatanController($kegiatanModel, $programModel);
    $subKegiatanController = new SubKegiatanController($subKegiatanModel, $kegiatanModel, $seksiModel);
    $seksiController = new SeksiController($seksiModel);
    $paguController = new PaguController($paguModel, $programModel, $kegiatanModel, $subKegiatanModel, $rekeningModel);
    $rekeningController = new RekeningController($rekeningModel, $programModel, $kegiatanModel, $subKegiatanModel);
    $rakController = new RakController($rakModel, $paguModel, $programModel, $kegiatanModel, $subKegiatanModel, $rekeningModel);
    $transaksiController = new TransaksiController($transaksiModel, $seksiModel, $paguModel, $rakModel, $programModel, $kegiatanModel, $subKegiatanModel, $rekeningModel);
    $dashboardController = new DashboardController($paguModel, $rakModel, $transaksiModel, $seksiModel, $programModel, $kegiatanModel, $subKegiatanModel);
    $authController = new AuthController();
    $dashboardSeksiController = new DashboardSeksiController();
    $seksiTransaksiController = new SeksiTransaksiController();
    $excelController = new ExcelController($paguModel, $rakModel, $transaksiModel, $seksiModel);

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

    // Define public routes
    $publicRoutes = ['/', '', '/dashboard', '/dashboard/', '/login', '/logout', '/export/laporan', '/export/serapan-bulanan', '/export/sisa-semester'];
    
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
            || preg_match('#^/seksi/transaksi#', $path);
        if (!$seksiAllowed) {
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

    // Route matching - Dashboard
    if ($path === '/' || $path === '' || $path === '/dashboard' || $path === '/dashboard/') {
        $dashboardController->index();
    }
    // Dashboard Seksi Routes
    elseif ($path === '/dashboard/tu' || $path === '/dashboard/tu/') {
        $dashboardSeksiController->showTU();
    }
    elseif ($path === '/dashboard/rlpm' || $path === '/dashboard/rlpm/') {
        $dashboardSeksiController->showRLPM();
    }
    elseif ($path === '/dashboard/tkuk' || $path === '/dashboard/tkuk/') {
        $dashboardSeksiController->showTKUK();
    }
    // Route matching - Excel Export
    elseif ($path === '/export/laporan') {
        $excelController->exportLaporan();
    }
    elseif ($path === '/export/serapan-bulanan') {
        $excelController->exportSerapanBulanan();
    }
    elseif ($path === '/export/sisa-semester') {
        $excelController->exportSisaSemester();
    }
    // Route matching - Program
    elseif ($path === '/program' || $path === '/program/') {
        $programController->index();
    } elseif ($path === '/program/create') {
        if ($requestMethod === 'GET') {
            $programController->create();
        } elseif ($requestMethod === 'POST') {
            $programController->store();
        }
    } elseif ($path === '/program/store' && $requestMethod === 'POST') {
        $programController->store();
    } elseif (preg_match('#^/program/edit/(\d+)$#', $path, $matches)) {
        $id = (int) $matches[1];
        if ($requestMethod === 'GET') {
            $programController->edit($id);
        } elseif ($requestMethod === 'POST') {
            $programController->update($id);
        }
    } elseif (preg_match('#^/program/update/(\d+)$#', $path, $matches) && $requestMethod === 'POST') {
        $id = (int) $matches[1];
        $programController->update($id);
    } elseif (preg_match('#^/program/delete/(\d+)$#', $path, $matches)) {
        $id = (int) $matches[1];
        $programController->delete($id);
    }
    // Route matching - Kegiatan
    elseif ($path === '/kegiatan' || $path === '/kegiatan/') {
        $kegiatanController->index();
    } elseif ($path === '/kegiatan/create') {
        if ($requestMethod === 'GET') {
            $kegiatanController->create();
        } elseif ($requestMethod === 'POST') {
            $kegiatanController->store();
        }
    } elseif ($path === '/kegiatan/store' && $requestMethod === 'POST') {
        $kegiatanController->store();
    } elseif (preg_match('#^/kegiatan/edit/(\d+)$#', $path, $matches)) {
        $id = (int) $matches[1];
        if ($requestMethod === 'GET') {
            $kegiatanController->edit($id);
        } elseif ($requestMethod === 'POST') {
            $kegiatanController->update($id);
        }
    } elseif (preg_match('#^/kegiatan/update/(\d+)$#', $path, $matches) && $requestMethod === 'POST') {
        $id = (int) $matches[1];
        $kegiatanController->update($id);
    } elseif (preg_match('#^/kegiatan/delete/(\d+)$#', $path, $matches)) {
        $id = (int) $matches[1];
        $kegiatanController->delete($id);
    }
    // Route matching - Sub Kegiatan
    elseif ($path === '/sub-kegiatan' || $path === '/sub-kegiatan/') {
        $subKegiatanController->index();
    } elseif ($path === '/sub-kegiatan/create') {
        if ($requestMethod === 'GET') {
            $subKegiatanController->create();
        } elseif ($requestMethod === 'POST') {
            $subKegiatanController->store();
        }
    } elseif ($path === '/sub-kegiatan/store' && $requestMethod === 'POST') {
        $subKegiatanController->store();
    } elseif (preg_match('#^/sub-kegiatan/edit/(\d+)$#', $path, $matches)) {
        $id = (int) $matches[1];
        if ($requestMethod === 'GET') {
            $subKegiatanController->edit($id);
        } elseif ($requestMethod === 'POST') {
            $subKegiatanController->update($id);
        }
    } elseif (preg_match('#^/sub-kegiatan/update/(\d+)$#', $path, $matches) && $requestMethod === 'POST') {
        $id = (int) $matches[1];
        $subKegiatanController->update($id);
    } elseif (preg_match('#^/sub-kegiatan/delete/(\d+)$#', $path, $matches)) {
        $id = (int) $matches[1];
        $subKegiatanController->delete($id);
    }
    // Route matching - Seksi
    elseif ($path === '/seksi' || $path === '/seksi/') {
        $seksiController->index();
    } elseif ($path === '/seksi/create') {
        if ($requestMethod === 'GET') {
            $seksiController->create();
        } elseif ($requestMethod === 'POST') {
            $seksiController->store();
        }
    } elseif ($path === '/seksi/store' && $requestMethod === 'POST') {
        $seksiController->store();
    } elseif (preg_match('#^/seksi/edit/(\d+)$#', $path, $matches)) {
        $id = (int) $matches[1];
        if ($requestMethod === 'GET') {
            $seksiController->edit($id);
        } elseif ($requestMethod === 'POST') {
            $seksiController->update($id);
        }
    } elseif (preg_match('#^/seksi/update/(\d+)$#', $path, $matches) && $requestMethod === 'POST') {
        $id = (int) $matches[1];
        $seksiController->update($id);
    } elseif (preg_match('#^/seksi/delete/(\d+)$#', $path, $matches)) {
        $id = (int) $matches[1];
        $seksiController->delete($id);
    }
    // Route matching - Pagu
    elseif ($path === '/pagu' || $path === '/pagu/') {
        $paguController->index();
    } elseif ($path === '/pagu/create') {
        if ($requestMethod === 'GET') {
            $paguController->create();
        } elseif ($requestMethod === 'POST') {
            $paguController->store();
        }
    } elseif ($path === '/pagu/store' && $requestMethod === 'POST') {
        $paguController->store();
    } elseif ($path === '/pagu/store-batch' && $requestMethod === 'POST') {
        $paguController->storeBatch();
    } elseif (preg_match('#^/pagu/edit/(\d+)$#', $path, $matches)) {
        $id = (int) $matches[1];
        if ($requestMethod === 'GET') {
            $paguController->edit($id);
        } elseif ($requestMethod === 'POST') {
            $paguController->update($id);
        }
    } elseif (preg_match('#^/pagu/update/(\d+)$#', $path, $matches) && $requestMethod === 'POST') {
        $id = (int) $matches[1];
        $paguController->update($id);
    } elseif (preg_match('#^/pagu/delete/(\d+)$#', $path, $matches)) {
        $id = (int) $matches[1];
        $paguController->delete($id);
    } elseif ($path === '/pagu/get-kegiatans' && $requestMethod === 'GET') {
        $paguController->getKegiatansByProgram();
    } elseif ($path === '/pagu/get-sub-kegiatans' && $requestMethod === 'GET') {
        $paguController->getSubKegiatansByKegiatan();
    } elseif ($path === '/pagu/get-rekenings' && $requestMethod === 'GET') {
        $paguController->getRekeningsBySubKegiatan();
    }
    // Route matching - Rekening
    elseif ($path === '/rekening' || $path === '/rekening/') {
        $rekeningController->index();
    } elseif ($path === '/rekening/create') {
        if ($requestMethod === 'GET') {
            $rekeningController->create();
        } elseif ($requestMethod === 'POST') {
            $rekeningController->store();
        }
    } elseif ($path === '/rekening/store' && $requestMethod === 'POST') {
        $rekeningController->store();
    } elseif ($path === '/rekening/store-batch' && $requestMethod === 'POST') {
        $rekeningController->storeBatch();
    } elseif (preg_match('#^/rekening/edit/(\d+)$#', $path, $matches)) {
        $id = (int) $matches[1];
        if ($requestMethod === 'GET') {
            $rekeningController->edit($id);
        } elseif ($requestMethod === 'POST') {
            $rekeningController->update($id);
        }
    } elseif (preg_match('#^/rekening/update/(\d+)$#', $path, $matches) && $requestMethod === 'POST') {
        $id = (int) $matches[1];
        $rekeningController->update($id);
    } elseif (preg_match('#^/rekening/delete/(\d+)$#', $path, $matches)) {
        $id = (int) $matches[1];
        $rekeningController->delete($id);
    }
    // Route matching - RAK
    elseif ($path === '/rak/rekap' || $path === '/rak/rekap/') {
        $rakController->rekap();
    } elseif ($path === '/rak' || $path === '/rak/') {
        $rakController->index();
    } elseif ($path === '/rak/create') {
        if ($requestMethod === 'GET') {
            $rakController->create();
        } elseif ($requestMethod === 'POST') {
            $rakController->store();
        }
    } elseif ($path === '/rak/store' && $requestMethod === 'POST') {
        $rakController->store();
    } elseif (preg_match('#^/rak/edit/(\d+)/(\d+)$#', $path, $matches)) {
        $rekeningId = (int) $matches[1];
        $tahun = (int) $matches[2];
        if ($requestMethod === 'GET') {
            $rakController->edit($rekeningId, $tahun);
        } elseif ($requestMethod === 'POST') {
            $rakController->update($rekeningId, $tahun);
        }
    } elseif (preg_match('#^/rak/update/(\d+)/(\d+)$#', $path, $matches) && $requestMethod === 'POST') {
        $rekeningId = (int) $matches[1];
        $tahun = (int) $matches[2];
        $rakController->update($rekeningId, $tahun);
    } elseif (preg_match('#^/rak/delete/(\d+)/(\d+)$#', $path, $matches)) {
        $rekeningId = (int) $matches[1];
        $tahun = (int) $matches[2];
        $rakController->delete($rekeningId, $tahun);
    } elseif ($path === '/rak/get-rekening-info' && $requestMethod === 'GET') {
        $rakController->getRekeningInfo();
    }
    // Route matching - Transaksi
    elseif ($path === '/transaksi' || $path === '/transaksi/') {
        $transaksiController->index();
    } elseif ($path === '/transaksi/create') {
        if ($requestMethod === 'GET') {
            $transaksiController->create();
        } elseif ($requestMethod === 'POST') {
            $transaksiController->store();
        }
    } elseif ($path === '/transaksi/store' && $requestMethod === 'POST') {
        $transaksiController->store();
    } elseif ($path === '/transaksi/store-batch' && $requestMethod === 'POST') {
        $transaksiController->storeBatch();
    } elseif (preg_match('#^/transaksi/edit/(\d+)$#', $path, $matches)) {
        $id = (int) $matches[1];
        if ($requestMethod === 'GET') {
            $transaksiController->edit($id);
        }
    } elseif (preg_match('#^/transaksi/update/(\d+)$#', $path, $matches)) {
        $id = (int) $matches[1];
        if ($requestMethod === 'POST') {
            // Handle update
            $transaksiController->update($id);
        }
    } elseif (preg_match('#^/transaksi/delete/(\d+)$#', $path, $matches)) {
        $id = (int) $matches[1];
        $transaksiController->delete($id);
    } elseif (preg_match('#^/transaksi/verifikasi/(\d+)$#', $path, $matches) && $requestMethod === 'POST') {
        $transaksiController->verifikasi((int) $matches[1]);
    } elseif (preg_match('#^/transaksi/tolak/(\d+)$#', $path, $matches) && $requestMethod === 'POST') {
        $transaksiController->tolak((int) $matches[1]);
    } elseif ($path === '/transaksi/get-remaining-pagu' && $requestMethod === 'GET') {
        $transaksiController->getRemainingPagu();
    } elseif ($path === '/transaksi/generate-no-bukti' && $requestMethod === 'GET') {
        $transaksiController->generateNomorBukti();
    } elseif ($path === '/transaksi/get-rekenings-with-budget' && $requestMethod === 'GET') {
        $transaksiController->getRekeningsWithBudget();
    }
    // Route matching - Input Transaksi Seksi (role seksi)
    elseif ($path === '/seksi/transaksi' || $path === '/seksi/transaksi/') {
        $seksiTransaksiController->index();
    } elseif ($path === '/seksi/transaksi/create') {
        $seksiTransaksiController->create();
    } elseif ($path === '/seksi/transaksi/store' && $requestMethod === 'POST') {
        $seksiTransaksiController->store();
    } elseif (preg_match('#^/seksi/transaksi/edit/(\d+)$#', $path, $matches)) {
        $seksiTransaksiController->edit((int) $matches[1]);
    } elseif (preg_match('#^/seksi/transaksi/update/(\d+)$#', $path, $matches) && $requestMethod === 'POST') {
        $seksiTransaksiController->update((int) $matches[1]);
    } elseif (preg_match('#^/seksi/transaksi/delete/(\d+)$#', $path, $matches) && $requestMethod === 'POST') {
        $seksiTransaksiController->delete((int) $matches[1]);
    } elseif ($path === '/seksi/transaksi/kegiatans' && $requestMethod === 'GET') {
        $seksiTransaksiController->getKegiatans();
    } elseif ($path === '/seksi/transaksi/subkegiatans' && $requestMethod === 'GET') {
        $seksiTransaksiController->getSubKegiatans();
    } elseif ($path === '/seksi/transaksi/rekenings' && $requestMethod === 'GET') {
        $seksiTransaksiController->getRekenings();
    } elseif ($path === '/seksi/transaksi/sisa-pagu' && $requestMethod === 'GET') {
        $seksiTransaksiController->getSisaPagu();
    } elseif ($path === '/seksi/transaksi/generate-no-bukti' && $requestMethod === 'GET') {
        $seksiTransaksiController->generateNomorBukti();
    } elseif ($path === '/seksi/transaksi/search-st' && $requestMethod === 'GET') {
        $seksiTransaksiController->searchSuratTugas();
    } elseif ($path === '/seksi/transaksi/pegawai-st' && $requestMethod === 'GET') {
        $seksiTransaksiController->getPegawaiSuratTugas();
    } else {
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
