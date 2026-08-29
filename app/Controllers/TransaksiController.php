<?php

namespace App\Controllers;

use App\Models\Transaksi;
use App\Models\Seksi;
use App\Models\Pagu;
use App\Models\Rak;
use App\Models\Program;
use App\Models\Kegiatan;
use App\Models\SubKegiatan;
use App\Models\Rekening;
use PDOException;

class TransaksiController
{
    private Transaksi $transaksiModel;
    private Seksi $seksiModel;
    private Pagu $paguModel;
    private Rak $rakModel;
    private Program $programModel;
    private Kegiatan $kegiatanModel;
    private SubKegiatan $subKegiatanModel;
    private Rekening $rekeningModel;

    public function __construct(
        Transaksi $transaksiModel,
        Seksi $seksiModel,
        Pagu $paguModel,
        Rak $rakModel,
        Program $programModel,
        Kegiatan $kegiatanModel,
        SubKegiatan $subKegiatanModel,
        Rekening $rekeningModel
    ) {
        $this->transaksiModel = $transaksiModel;
        $this->seksiModel = $seksiModel;
        $this->paguModel = $paguModel;
        $this->rakModel = $rakModel;
        $this->programModel = $programModel;
        $this->kegiatanModel = $kegiatanModel;
        $this->subKegiatanModel = $subKegiatanModel;
        $this->rekeningModel = $rekeningModel;
    }

    /**
     * Display list of transactions
     */
    public function index(): void
    {
        try {
            // Read all filter params
            $filterBulan      = isset($_GET['bulan'])          && $_GET['bulan']          !== '' ? (int) $_GET['bulan']          : null;
            $filterTahun      = isset($_GET['tahun'])          && $_GET['tahun']          !== '' ? (int) $_GET['tahun']          : (int) date('Y');
            $filterKegiatan   = isset($_GET['kegiatan_id'])    && $_GET['kegiatan_id']    !== '' ? (int) $_GET['kegiatan_id']    : null;
            $filterSubKegiatan = isset($_GET['sub_kegiatan_id']) && $_GET['sub_kegiatan_id'] !== '' ? (int) $_GET['sub_kegiatan_id'] : null;
            $filterStatus      = isset($_GET['status']) && $_GET['status'] !== '' ? $_GET['status'] : null;

            // Check if any filter is active
            $hasFilter = $filterBulan !== null || $filterKegiatan !== null || $filterSubKegiatan !== null || $filterStatus !== null;

            if ($hasFilter) {
                $transaksis = $this->transaksiModel->getWithFilters($filterBulan, $filterTahun, $filterKegiatan, $filterSubKegiatan, $filterStatus);
            } else {
                $transaksis = $this->transaksiModel->getAll();
            }

            // Load kegiatan & sub_kegiatan for filter dropdowns
            $filterKegiatanList    = $this->kegiatanModel->getAll();
            $filterSubKegiatanList = $this->subKegiatanModel->getAll();

            $perPage = 10;
            $page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
            $total = count($transaksis);
            $totalPages = max(1, (int) ceil($total / $perPage));
            if ($page > $totalPages) {
                $page = $totalPages;
            }
            $offset = ($page - 1) * $perPage;
            $transaksis = array_slice($transaksis, $offset, $perPage);

            // Build baseUrl with filter params for pagination links
            $queryParams = array_filter([
                'bulan'           => $filterBulan,
                'tahun'           => $filterTahun,
                'kegiatan_id'     => $filterKegiatan,
                'sub_kegiatan_id' => $filterSubKegiatan,
                'status'          => $filterStatus,
            ], fn($v) => $v !== null);
            $paginationBase = base_url('transaksi') . '?' . http_build_query($queryParams) . '&';

            $pagination = [
                'page'       => $page,
                'perPage'    => $perPage,
                'total'      => $total,
                'totalPages' => $totalPages,
                'baseUrl'    => rtrim($paginationBase, '&'),
            ];

            $pageTitle  = 'Transaksi';
            $activePage = 'transaksi';
            $viewFile   = __DIR__ . '/../../views/transaksi/index.php';

            include __DIR__ . '/../../views/layout.php';
        } catch (\Exception $e) {
            $this->handleError('Failed to load transactions: ' . $e->getMessage());
        }
    }

    /**
     * Show form for creating new transaction
     */
    public function create(): void
    {
        try {
            $programs = $this->programModel->getAll();

            $pageTitle = 'Tambah Transaksi';
            $activePage = 'transaksi';
            $viewFile = __DIR__ . '/../../views/transaksi/form.php';
            $transaksi = null;
            $action = 'store';

            include __DIR__ . '/../../views/layout.php';
        } catch (\Exception $e) {
            $this->handleError('Failed to load form: ' . $e->getMessage());
        }
    }

    /**
     * Store new transaction
     */
    public function store(): void
    {
        // Get seksi_id from rekening
        if (empty($_POST['rekening_id'])) {
            $errors = ['rekening_id' => 'Rekening wajib dipilih'];
            try {
                $programs = $this->programModel->getAll();
                $this->showFormWithErrors($errors, $_POST, [], $programs);
            } catch (\Exception $e) {
                $this->handleError('Failed to load form: ' . $e->getMessage());
            }
            return;
        }

        $rekening = $this->rekeningModel->getById((int) $_POST['rekening_id']);
        if (!$rekening) {
            $errors = ['rekening_id' => 'Rekening tidak valid'];
            try {
                $programs = $this->programModel->getAll();
                $this->showFormWithErrors($errors, $_POST, [], $programs);
            } catch (\Exception $e) {
                $this->handleError('Failed to load form: ' . $e->getMessage());
            }
            return;
        }

        // Dapatkan seksi_id: dari rekening (via JOIN sub_kegiatan) atau langsung dari sub_kegiatan
        $seksiId = $rekening['seksi_id'] ?? null;
        if (empty($seksiId) && !empty($rekening['sub_kegiatan_id'])) {
            $subKegiatan = $this->subKegiatanModel->getById((int) $rekening['sub_kegiatan_id']);
            $seksiId = $subKegiatan['seksi_id'] ?? null;
        }

        if (empty($seksiId)) {
            $errors = ['rekening_id' => 'Rekening tidak memiliki seksi — pastikan Sub Kegiatan sudah dikonfigurasi dengan benar'];
            try {
                $programs = $this->programModel->getAll();
                $this->showFormWithErrors($errors, $_POST, [], $programs);
            } catch (\Exception $e) {
                $this->handleError('Failed to load form: ' . $e->getMessage());
            }
            return;
        }

        // Set seksi_id from rekening
        $_POST['seksi_id'] = $seksiId;

        $errors = $this->validate($_POST);

        if (!empty($errors)) {
            try {
                $programs = $this->programModel->getAll();
                $this->showFormWithErrors($errors, $_POST, [], $programs);
            } catch (\Exception $e) {
                $this->handleError('Failed to load form: ' . $e->getMessage());
            }
            return;
        }

        try {
            $this->transaksiModel->create(
                $_POST['tanggal'],
                (int) $rekening['seksi_id'],
                (int) $_POST['rekening_id'],
                trim($_POST['uraian']),
                (float) str_replace(['.', ','], '', $_POST['nilai']),
                trim($_POST['nomor_bukti'])
            );

            $this->redirectWithMessage(base_url('transaksi'), 'success', 'Transaksi berhasil ditambahkan');
        } catch (\Exception $e) {
            $this->handleError('Gagal menambahkan transaksi: ' . $e->getMessage());
        }
    }

    /**
     * Store multiple transactions in batch
     */
    public function storeBatch(): void
    {
        // Filter out empty/zero value rows
        if (isset($_POST['rekenings']) && is_array($_POST['rekenings'])) {
            $_POST['rekenings'] = array_filter($_POST['rekenings'], function ($rekening) {
                if (empty($rekening['rekening_id'])) {
                    return false;
                }
                $nilai = str_replace(['.', ','], '', $rekening['nilai'] ?? '');
                return $nilai !== '' && (float)$nilai > 0;
            });
        }

        // Validate common fields
        $errors = [];

        if (empty($_POST['tanggal'])) {
            $errors['tanggal'] = 'Tanggal wajib diisi';
        } elseif (!strtotime($_POST['tanggal'])) {
            $errors['tanggal'] = 'Format tanggal tidak valid';
        }

        // Validate rekenings array
        if (empty($_POST['rekenings']) || !is_array($_POST['rekenings'])) {
            $errors['rekenings'] = 'Minimal harus ada 1 rekening';
        } else {
            foreach ($_POST['rekenings'] as $index => $rekening) {
                if (empty($rekening['rekening_id'])) {
                    $errors['rekenings'][$index]['rekening_id'] = 'Rekening wajib dipilih';
                } else {
                    // Validate rekening exists and has seksi_id
                    $rekeningData = $this->rekeningModel->getById((int) $rekening['rekening_id']);
                    if (!$rekeningData || empty($rekeningData['seksi_id'])) {
                        $errors['rekenings'][$index]['rekening_id'] = 'Rekening tidak valid atau tidak memiliki seksi';
                    }
                }
                if (empty(trim($rekening['uraian'] ?? ''))) {
                    $errors['rekenings'][$index]['uraian'] = 'Uraian wajib diisi';
                }
                if (empty($rekening['nilai'])) {
                    $errors['rekenings'][$index]['nilai'] = 'Nilai wajib diisi';
                } else {
                    $nilai = str_replace(['.', ','], '', $rekening['nilai']);
                    if (!is_numeric($nilai) || (float) $nilai <= 0) {
                        $errors['rekenings'][$index]['nilai'] = 'Nilai harus berupa angka lebih dari 0';
                    }
                }
                if (empty(trim($rekening['nomor_bukti'] ?? ''))) {
                    $errors['rekenings'][$index]['nomor_bukti'] = 'Nomor bukti wajib diisi';
                }
            }
        }

        if (!empty($errors)) {
            try {
                $programs = $this->programModel->getAll();
                $this->showBatchFormWithErrors($errors, $_POST, [], $programs);
            } catch (\Exception $e) {
                $this->handleError('Failed to load form: ' . $e->getMessage());
            }
            return;
        }

        try {
            $tanggal = $_POST['tanggal'];
            $successCount = 0;
            $errorMessages = [];

            foreach ($_POST['rekenings'] as $rekening) {
                try {
                    // Get seksi_id from rekening
                    $rekeningId = (int) $rekening['rekening_id'];
                    $rekeningData = $this->rekeningModel->getById($rekeningId);

                    if (!$rekeningData) {
                        $errorMessages[] = "Rekening ID {$rekeningId}: Rekening tidak valid";
                        continue;
                    }

                    // Dapatkan seksi_id: dari rekening (JOIN) atau fallback langsung dari sub_kegiatan
                    $seksiIdRaw = $rekeningData['seksi_id'] ?? null;
                    if (empty($seksiIdRaw) && !empty($rekeningData['sub_kegiatan_id'])) {
                        $subKeg = $this->subKegiatanModel->getById((int) $rekeningData['sub_kegiatan_id']);
                        $seksiIdRaw = $subKeg['seksi_id'] ?? null;
                    }

                    if (empty($seksiIdRaw)) {
                        $errorMessages[] = "Rekening ID {$rekeningId}: Sub Kegiatan tidak memiliki seksi";
                        continue;
                    }

                    $seksiId = (int) $seksiIdRaw;

                    // Validate pagu for each transaction
                    $tahun = (int) date('Y', strtotime($tanggal));
                    $nilai = (float) str_replace(['.', ','], '', $rekening['nilai']);

                    // Check pagu
                    $pagus = $this->paguModel->getAll();
                    $pagu = null;
                    foreach ($pagus as $p) {
                        if ($p['rekening_id'] == $rekeningId && $p['tahun'] == $tahun) {
                            $pagu = $p;
                            break;
                        }
                    }

                    if (!$pagu) {
                        // Pagu belum ditetapkan — tetap izinkan transaksi, catat peringatan
                        error_log("Warning storeBatch: Pagu belum ditetapkan untuk rekening_id={$rekeningId} tahun={$tahun}");
                    } else {
                        // Cek sisa: bandingkan pagu vs total transaksi SAJA (RAK = rencana, bukan pengurang)
                        $totalTransaksi = $this->transaksiModel->getTotalByRekeningAndYear($rekeningId, $tahun);
                        $sisaPagu = (float) $pagu['nilai_pagu'] - $totalTransaksi;

                        if ($nilai > $sisaPagu + 0.01) {
                            $errorMessages[] = sprintf(
                                'Rekening ID %d: nilai (Rp %s) melebihi sisa anggaran (Rp %s)',
                                $rekeningId,
                                number_format($nilai, 0, ',', '.'),
                                number_format($sisaPagu, 0, ',', '.')
                            );
                            continue;
                        }
                    }

                    // Create transaction
                    $this->transaksiModel->create(
                        $tanggal,
                        $seksiId,
                        $rekeningId,
                        trim($rekening['uraian']),
                        $nilai,
                        trim($rekening['nomor_bukti'])
                    );
                    $successCount++;
                } catch (\Exception $e) {
                    $errorMessages[] = "Error pada rekening ID {$rekening['rekening_id']}: " . $e->getMessage();
                }
            }

            if ($successCount > 0) {
                $message = "Berhasil menambahkan {$successCount} transaksi";
                if (!empty($errorMessages)) {
                    $message .= ". " . count($errorMessages) . " transaksi gagal: " . implode(', ', array_slice($errorMessages, 0, 3));
                }
                $this->redirectWithMessage(base_url('transaksi'), 'success', $message);
            } else {
                $this->redirectWithMessage(base_url('transaksi'), 'error', 'Gagal menambahkan transaksi: ' . implode(', ', $errorMessages));
            }
        } catch (\Exception $e) {
            $this->handleError('Gagal menambahkan transaksi: ' . $e->getMessage());
        }
    }

    /**
     * Show form for editing transaction
     */
    public function edit(int $id): void
    {
        try {
            $transaksi = $this->transaksiModel->getById($id);

            if (!$transaksi) {
                $this->redirectWithMessage(base_url('transaksi'), 'error', 'Transaksi tidak ditemukan');
                return;
            }

            // Get rekening to find its hierarchy
            $rekening = $this->rekeningModel->getById((int) $transaksi['rekening_id']);

            if (!$rekening) {
                $this->redirectWithMessage(base_url('transaksi'), 'error', 'Data rekening tidak ditemukan');
                return;
            }

            $programs = $this->programModel->getAll();
            $kegiatans = $this->kegiatanModel->getByProgramId((int) ($rekening['program_id'] ?? 0));
            $subKegiatans = $this->subKegiatanModel->getByKegiatanId((int) ($rekening['kegiatan_id'] ?? 0));
            $rekenings = $this->rekeningModel->getBySubKegiatanId((int) ($rekening['sub_kegiatan_id'] ?? 0));

            // Merge transaction with hierarchy IDs for the form
            $transaksi['program_id'] = $rekening['program_id'] ?? '';
            $transaksi['kegiatan_id'] = $rekening['kegiatan_id'] ?? '';
            $transaksi['sub_kegiatan_id'] = $rekening['sub_kegiatan_id'] ?? '';

            // Pass data to view
            $pageTitle = 'Edit Transaksi';
            $activePage = 'transaksi';
            $viewFile = __DIR__ . '/../../views/transaksi/form.php';
            $action = 'update';

            include __DIR__ . '/../../views/layout.php';
        } catch (\Exception $e) {
            error_log('Error in edit transaksi: ' . $e->getMessage());
            $this->handleError('Gagal memuat transaksi: ' . $e->getMessage());
        }
    }

    /**
     * Update transaction
     */
    public function update(int $id): void
    {
        // Get existing transaction first
        $existingTransaksi = $this->transaksiModel->getById($id);
        if (!$existingTransaksi) {
            $this->redirectWithMessage(base_url('transaksi'), 'error', 'Transaksi tidak ditemukan');
            return;
        }

        // Get seksi_id from rekening
        if (empty($_POST['rekening_id'])) {
            $errors = ['rekening_id' => 'Rekening wajib dipilih'];
            try {
                $rekening = $this->rekeningModel->getById((int) $existingTransaksi['rekening_id']);
                $programs = $this->programModel->getAll();
                $kegiatans = $this->kegiatanModel->getByProgramId((int) ($rekening['program_id'] ?? 0));
                $subKegiatans = $this->subKegiatanModel->getByKegiatanId((int) ($rekening['kegiatan_id'] ?? 0));
                $rekenings = $this->rekeningModel->getBySubKegiatanId((int) ($rekening['sub_kegiatan_id'] ?? 0));

                $transaksi = array_merge($existingTransaksi, $_POST);
                $transaksi['program_id'] = $rekening['program_id'] ?? '';
                $transaksi['kegiatan_id'] = $rekening['kegiatan_id'] ?? '';
                $transaksi['sub_kegiatan_id'] = $rekening['sub_kegiatan_id'] ?? '';

                $this->showFormWithErrors($errors, $transaksi, [], $programs, $kegiatans, $subKegiatans, $rekenings);
            } catch (\Exception $e) {
                $this->handleError('Failed to load form: ' . $e->getMessage());
            }
            return;
        }

        $rekening = $this->rekeningModel->getById((int) $_POST['rekening_id']);
        if (!$rekening || empty($rekening['seksi_id'])) {
            $errors = ['rekening_id' => 'Rekening tidak valid atau tidak memiliki seksi'];
            try {
                $oldRekening = $this->rekeningModel->getById((int) $existingTransaksi['rekening_id']);
                $programs = $this->programModel->getAll();
                $kegiatans = $this->kegiatanModel->getByProgramId((int) ($oldRekening['program_id'] ?? 0));
                $subKegiatans = $this->subKegiatanModel->getByKegiatanId((int) ($oldRekening['kegiatan_id'] ?? 0));
                $rekenings = $this->rekeningModel->getBySubKegiatanId((int) ($oldRekening['sub_kegiatan_id'] ?? 0));

                $transaksi = array_merge($existingTransaksi, $_POST);
                $transaksi['program_id'] = $oldRekening['program_id'] ?? '';
                $transaksi['kegiatan_id'] = $oldRekening['kegiatan_id'] ?? '';
                $transaksi['sub_kegiatan_id'] = $oldRekening['sub_kegiatan_id'] ?? '';

                $this->showFormWithErrors($errors, $transaksi, [], $programs, $kegiatans, $subKegiatans, $rekenings);
            } catch (\Exception $e) {
                $this->handleError('Failed to load form: ' . $e->getMessage());
            }
            return;
        }

        // Set seksi_id from rekening
        $_POST['seksi_id'] = $rekening['seksi_id'];

        $errors = $this->validate($_POST, $id);

        if (!empty($errors)) {
            try {
                $programs = $this->programModel->getAll();
                $kegiatans = $this->kegiatanModel->getByProgramId((int) ($rekening['program_id'] ?? 0));
                $subKegiatans = $this->subKegiatanModel->getByKegiatanId((int) ($rekening['kegiatan_id'] ?? 0));
                $rekenings = $this->rekeningModel->getBySubKegiatanId((int) ($rekening['sub_kegiatan_id'] ?? 0));

                $transaksi = array_merge($existingTransaksi, $_POST);
                $transaksi['program_id'] = $rekening['program_id'] ?? '';
                $transaksi['kegiatan_id'] = $rekening['kegiatan_id'] ?? '';
                $transaksi['sub_kegiatan_id'] = $rekening['sub_kegiatan_id'] ?? '';

                $this->showFormWithErrors($errors, $transaksi, [], $programs, $kegiatans, $subKegiatans, $rekenings);
            } catch (\Exception $e) {
                $this->handleError('Failed to load form: ' . $e->getMessage());
            }
            return;
        }

        try {
            // Ensure session is started
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }

            error_log("Updating transaksi ID {$id} with data: " . json_encode($_POST));

            // Debug: Check if we have all necessary data
            error_log("Debug - POST data: " . json_encode($_POST));
            error_log("Debug - ID: " . $id);
            error_log("Debug - Rekening: " . json_encode($rekening));

            // Execute update
            $result = $this->transaksiModel->update(
                $id,
                $_POST['tanggal'],
                (int) $rekening['seksi_id'],
                (int) $_POST['rekening_id'],
                trim($_POST['uraian']),
                (float) str_replace(['.', ','], '', $_POST['nilai']),
                trim($_POST['nomor_bukti'])
            );

            // Debug: Check update result
            error_log("Debug - Update result: " . ($result ? 'success' : 'failed'));

            // Cek apakah update berhasil
            if (!$result) {
                error_log("Update gagal: transaksi ID {$id} tidak diupdate");
                $this->handleError('Gagal memperbarui transaksi: Data tidak berubah');
                return;
            }

            // Redirect dengan flash message
            $_SESSION['flash_message'] = 'Transaksi berhasil diperbarui';
            $_SESSION['flash_type'] = 'success';

            $redirectUrl = base_url('transaksi');
            error_log("Redirecting to: " . $redirectUrl);

            // Immediately redirect without any other output
            if (!headers_sent()) {
                ob_end_clean(); // Clear all output buffers
                header('Location: ' . $redirectUrl);
                error_log("HTTP redirect sent to " . $redirectUrl);
                die();
            } else {
                error_log("Headers already sent, using JavaScript redirect");
                echo '<!DOCTYPE html>';
                echo '<html><head><script>window.location.href = "' . $redirectUrl . '";</script></head><body></body></html>';
                die();
            }
        } catch (\Exception $e) {
            error_log('Update transaksi error: ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());
            $this->handleError('Gagal memperbarui transaksi: ' . $e->getMessage());
        }
    }

    /**
     * Delete transaction
     */
    public function delete(int $id): void
    {
        try {
            $this->transaksiModel->delete($id);
            $this->redirectWithMessage(base_url('transaksi'), 'success', 'Transaksi berhasil dihapus');
        } catch (\Exception $e) {
            $this->redirectWithMessage(base_url('transaksi'), 'error', 'Gagal menghapus transaksi: ' . $e->getMessage());
        }
    }

    /**
     * AJAX: Get remaining pagu for rekening and year
     */
    public function getRemainingPagu(): void
    {
        header('Content-Type: application/json');
        try {
            $rekeningId = (int) ($_GET['rekening_id'] ?? 0);
            $tahun = (int) ($_GET['tahun'] ?? 0);

            if (!$rekeningId || !$tahun) {
                echo json_encode(['error' => 'Rekening ID and Tahun are required']);
                exit;
            }

            // Get pagu
            $pagus = $this->paguModel->getAll();
            $pagu = null;
            foreach ($pagus as $p) {
                if ($p['rekening_id'] == $rekeningId && $p['tahun'] == $tahun) {
                    $pagu = $p;
                    break;
                }
            }

            if (!$pagu) {
                echo json_encode([
                    'pagu' => null,
                    'total_rak' => 0,
                    'total_transaksi' => 0,
                    'remaining_pagu' => 0,
                    'message' => 'Pagu untuk rekening dan tahun ini belum ditetapkan'
                ]);
                exit;
            }

            // Get total RAK
            $totalRak = $this->rakModel->getTotalByRekeningAndYear($rekeningId, $tahun);

            // Get total transactions
            $totalTransaksi = $this->transaksiModel->getTotalByRekeningAndYear($rekeningId, $tahun);

            // Calculate remaining pagu
            $remainingPagu = (float) $pagu['nilai_pagu'] - $totalRak - $totalTransaksi;

            echo json_encode([
                'pagu' => (float) $pagu['nilai_pagu'],
                'total_rak' => $totalRak,
                'total_transaksi' => $totalTransaksi,
                'remaining_pagu' => $remainingPagu
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * AJAX: Get accounts for a sub-activity with budget information for a specific year
     */
    public function getRekeningsWithBudget(): void
    {
        header('Content-Type: application/json');
        try {
            $subKegiatanId = (int) ($_GET['sub_kegiatan_id'] ?? 0);
            $tahun = (int) ($_GET['tahun'] ?? (int)date('Y'));

            if (!$subKegiatanId) {
                echo json_encode(['error' => 'Sub Kegiatan ID is required']);
                exit;
            }

            $rekenings = $this->rekeningModel->getBySubKegiatanId($subKegiatanId);

            // Get all pagus for mapping
            $pagus = $this->paguModel->getAll();
            $paguMap = [];
            foreach ($pagus as $p) {
                if ((int)$p['tahun'] === $tahun) {
                    $paguMap[(int)$p['rekening_id']] = (float) $p['nilai_pagu'];
                }
            }

            $result = [];
            foreach ($rekenings as $rekening) {
                $rekeningId = (int) $rekening['id'];
                $paguValue = $paguMap[$rekeningId] ?? null;
                $totalTransaksi = $this->transaksiModel->getTotalByRekeningAndYear($rekeningId, $tahun);
                $sisaPagu = $paguValue !== null ? ($paguValue - $totalTransaksi) : null;

                $result[] = [
                    'id' => $rekeningId,
                    'kode_rekening' => $rekening['kode_rekening'],
                    'nama_rekening' => $rekening['nama_rekening'],
                    'seksi_id' => $rekening['seksi_id'],
                    'pagu' => $paguValue,
                    'total_transaksi' => $totalTransaksi,
                    'sisa_pagu' => $sisaPagu
                ];
            }

            echo json_encode($result);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * Validate form data
     * 
     * @param array $data
     * @param int|null $excludeId For update validation
     * @return array Errors
     */
    private function validate(array $data, ?int $excludeId = null): array
    {
        $errors = [];

        // Validate tanggal
        if (empty($data['tanggal'])) {
            $errors['tanggal'] = 'Tanggal wajib diisi';
        } elseif (!strtotime($data['tanggal'])) {
            $errors['tanggal'] = 'Format tanggal tidak valid';
        }

        // Validate seksi_id
        if (empty($data['seksi_id'])) {
            $errors['seksi_id'] = 'Seksi wajib dipilih';
        } elseif (!is_numeric($data['seksi_id'])) {
            $errors['seksi_id'] = 'Seksi tidak valid';
        }

        // Validate rekening_id
        if (empty($data['rekening_id'])) {
            $errors['rekening_id'] = 'Rekening wajib dipilih';
        } elseif (!is_numeric($data['rekening_id'])) {
            $errors['rekening_id'] = 'Rekening tidak valid';
        }

        // Validate uraian
        if (empty($data['uraian'])) {
            $errors['uraian'] = 'Uraian wajib diisi';
        } elseif (strlen(trim($data['uraian'])) > 500) {
            $errors['uraian'] = 'Uraian maksimal 500 karakter';
        }

        // Validate nilai
        if (empty($data['nilai'])) {
            $errors['nilai'] = 'Nilai wajib diisi';
        } else {
            $nilai = str_replace(['.', ','], '', $data['nilai']);
            if (!is_numeric($nilai)) {
                $errors['nilai'] = 'Nilai harus berupa angka';
            } elseif ((float) $nilai <= 0) {
                $errors['nilai'] = 'Nilai harus lebih dari 0';
            }
        }

        // Validate nomor_bukti
        if (empty($data['nomor_bukti'])) {
            $errors['nomor_bukti'] = 'Nomor bukti wajib diisi';
        } elseif (strlen(trim($data['nomor_bukti'])) > 100) {
            $errors['nomor_bukti'] = 'Nomor bukti maksimal 100 karakter';
        }

        // Check remaining pagu
        if (empty($errors['rekening_id']) && empty($errors['tanggal']) && empty($errors['nilai'])) {
            $rekeningId = (int) $data['rekening_id'];
            $tanggal    = $data['tanggal'];
            $tahun      = (int) date('Y', strtotime($tanggal));
            $nilai      = (float) str_replace(['.', ','], '', $data['nilai']);

            // Get pagu
            $pagus = $this->paguModel->getAll();
            $pagu  = null;
            foreach ($pagus as $p) {
                if ($p['rekening_id'] == $rekeningId && $p['tahun'] == $tahun) {
                    $pagu = $p;
                    break;
                }
            }

            if (!$pagu) {
                // Pagu belum ditetapkan — tampilkan peringatan tapi jangan blokir simpan
                // (transaksi tetap bisa diinput meskipun pagu belum ada)
                error_log("Warning: Pagu belum ditetapkan untuk rekening_id={$rekeningId} tahun={$tahun}");
            } else {
                // Bandingkan hanya dengan totalTransaksi (bukan dikurangi RAK)
                // karena RAK adalah rencana, transaksi adalah realisasi aktual
                $totalTransaksi = $this->transaksiModel->getTotalByRekeningAndYear($rekeningId, $tahun);

                // Kurangi nilai transaksi saat ini jika update
                if ($excludeId) {
                    $currentTransaksi = $this->transaksiModel->getById($excludeId);
                    if (
                        $currentTransaksi && $currentTransaksi['rekening_id'] == $rekeningId &&
                        (int) date('Y', strtotime($currentTransaksi['tanggal'])) == $tahun
                    ) {
                        $totalTransaksi -= (float) $currentTransaksi['nilai'];
                    }
                }

                $sisaPagu = (float) $pagu['nilai_pagu'] - $totalTransaksi;

                if ($nilai > $sisaPagu + 0.01) { // toleransi pembulatan 1 sen
                    $errors['nilai'] = sprintf(
                        'Nilai transaksi (Rp %s) melebihi sisa anggaran yang tersedia (Rp %s)',
                        number_format($nilai, 0, ',', '.'),
                        number_format($sisaPagu, 0, ',', '.')
                    );
                }
            }
        }

        return $errors;
    }

    /**
     * Show form with validation errors
     */
    private function showFormWithErrors(array $errors, array $data, array $seksis, array $programs, array $kegiatans = [], array $subKegiatans = [], array $rekenings = []): void
    {
        // $seksis parameter kept for backward compatibility but not used
        $pageTitle = isset($data['id']) ? 'Edit Transaksi' : 'Tambah Transaksi';
        $activePage = 'transaksi';
        $viewFile = __DIR__ . '/../../views/transaksi/form.php';
        $transaksi = $data;
        $action = isset($data['id']) ? 'update' : 'store';
        $validationErrors = $errors;

        include __DIR__ . '/../../views/layout.php';
    }

    /**
     * Show batch form with validation errors
     */
    private function showBatchFormWithErrors(array $errors, array $data, array $seksis, array $programs): void
    {
        // $seksis parameter kept for backward compatibility but not used
        try {
            // Get programs if not provided
            if (empty($programs)) {
                $programs = $this->programModel->getAll();
            }

            $pageTitle = 'Tambah Transaksi';
            $activePage = 'transaksi';
            $viewFile = __DIR__ . '/../../views/transaksi/form.php';
            $transaksi = null;
            $action = 'store';
            $validationErrors = $errors;
            $batchData = $data;

            include __DIR__ . '/../../views/layout.php';
        } catch (\Exception $e) {
            $this->handleError('Failed to load form: ' . $e->getMessage());
        }
    }

    /**
     * Redirect with flash message
     */
    private function redirectWithMessage(string $url, string $type, string $message): void
    {
        // Ensure session is started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION['flash_message'] = $message;
        $_SESSION['flash_type'] = $type;
        header('Location: ' . $url);
        exit;
    }

    /**
     * Verifikasi transaksi oleh admin (status -> 'diverifikasi')
     */
    public function verifikasi(int $id): void
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $ok = $this->transaksiModel->verifikasi($id, 'diverifikasi', $userId, '');
        $this->logAudit($userId, 'verifikasi_transaksi', 'transaksi', $id, 'Verifikasi transaksi id ' . $id);
        $this->redirectWithMessage(base_url('transaksi'), $ok ? 'success' : 'error', $ok ? 'Transaksi berhasil diverifikasi' : 'Gagal memverifikasi transaksi');
    }

    /**
     * Tolak transaksi oleh admin (status -> 'ditolak') wajib catatan
     */
    public function tolak(int $id): void
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $catatan = trim($_POST['catatan_verifikasi'] ?? '');
        if ($catatan === '') {
            $this->redirectWithMessage(base_url('transaksi'), 'error', 'Catatan penolakan wajib diisi');
            return;
        }
        $ok = $this->transaksiModel->verifikasi($id, 'ditolak', $userId, $catatan);
        $this->logAudit($userId, 'tolak_transaksi', 'transaksi', $id, 'Tolak transaksi id ' . $id . ' - ' . $catatan);
        $this->redirectWithMessage(base_url('transaksi'), $ok ? 'success' : 'error', $ok ? 'Transaksi ditolak' : 'Gagal menolak transaksi');
    }

    private function logAudit(int $userId, string $aksi, string $tabel, ?int $recordId, string $keterangan): void
    {
        try {
            $db = \Database::getConnection();
            $stmt = $db->prepare('INSERT INTO audit_log (user_id, aksi, tabel, record_id, keterangan) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$userId, $aksi, $tabel, $recordId, $keterangan]);
        } catch (\Exception $e) {
            error_log('audit_log error: ' . $e->getMessage());
        }
    }

    /**
     * Handle errors
     */
    private function handleError(string $message): void
    {
        error_log($message);
        // Tampilkan pesan error yang sebenarnya, bukan generic
        $this->redirectWithMessage(base_url('transaksi'), 'error', $message);
    }
}
