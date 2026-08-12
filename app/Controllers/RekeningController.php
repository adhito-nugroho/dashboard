<?php

namespace App\Controllers;

use App\Models\Rekening;
use App\Models\Program;
use App\Models\Kegiatan;
use App\Models\SubKegiatan;

class RekeningController {
    private Rekening $rekeningModel;
    private Program $programModel;
    private Kegiatan $kegiatanModel;
    private SubKegiatan $subKegiatanModel;
    
    public function __construct(
        Rekening $rekeningModel,
        Program $programModel,
        Kegiatan $kegiatanModel,
        SubKegiatan $subKegiatanModel
    ) {
        $this->rekeningModel = $rekeningModel;
        $this->programModel = $programModel;
        $this->kegiatanModel = $kegiatanModel;
        $this->subKegiatanModel = $subKegiatanModel;
    }
    
    /**
     * Display list of accounts
     */
    public function index(): void {
        try {
            $rekenings = $this->rekeningModel->getAll();
            
            // Simple pagination
            $perPage = 10;
            $page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
            $total = count($rekenings);
            $totalPages = max(1, (int) ceil($total / $perPage));
            if ($page > $totalPages) {
                $page = $totalPages;
            }
            $offset = ($page - 1) * $perPage;
            $rekenings = array_slice($rekenings, $offset, $perPage);
            
            $pagination = [
                'page' => $page,
                'perPage' => $perPage,
                'total' => $total,
                'totalPages' => $totalPages,
                'baseUrl' => base_url('rekening')
            ];
            
            $pageTitle = 'Rekening';
            $activePage = 'rekening';
            $viewFile = __DIR__ . '/../../views/rekening/index.php';
            
            include __DIR__ . '/../../views/layout.php';
        } catch (\Exception $e) {
            $this->handleError('Failed to load accounts: ' . $e->getMessage());
        }
    }

    /**
     * Show form for creating new account
     */
    public function create(): void {
        try {
            $programs = $this->programModel->getAll();
            
            $pageTitle = 'Tambah Rekening';
            $activePage = 'rekening';
            $viewFile = __DIR__ . '/../../views/rekening/form.php';
            $rekening = null; // new record
            $action = 'store';
            
            include __DIR__ . '/../../views/layout.php';
        } catch (\Exception $e) {
            $this->handleError('Failed to load form: ' . $e->getMessage());
        }
    }

    /**
     * Store new account
     */
    public function store(): void {
        $errors = $this->validate($_POST);
        
        if (!empty($errors)) {
            try {
                $programs = $this->programModel->getAll();
                $this->showFormWithErrors($errors, $_POST, $programs);
            } catch (\Exception $e) {
                $this->handleError('Failed to load form: ' . $e->getMessage());
            }
            return;
        }
        
        try {
            $this->rekeningModel->create(
                (int) $_POST['sub_kegiatan_id'],
                trim($_POST['kode_rekening']),
                trim($_POST['nama_rekening'])
            );
            
            $this->redirectWithMessage(base_url('rekening'), 'success', 'Rekening berhasil ditambahkan');
        } catch (\Exception $e) {
            $this->handleError('Gagal menambahkan rekening: ' . $e->getMessage());
        }
    }
    
    /**
     * Store multiple accounts in batch
     */
    public function storeBatch(): void {
        // Debug: Log raw POST data
        error_log('=== STORE BATCH DEBUG ===');
        error_log('max_input_vars: ' . ini_get('max_input_vars'));
        error_log('POST keys: ' . implode(', ', array_keys($_POST)));
        error_log('Raw POST rekenings count: ' . (isset($_POST['rekenings']) ? count($_POST['rekenings']) : 0));
        
        // Check if we're getting truncated data - check raw input
        $rawInput = file_get_contents('php://input');
        error_log('Raw input length: ' . strlen($rawInput));
        
        // Check PHP max_input_vars limit
        $maxInputVars = ini_get('max_input_vars');
        if ($maxInputVars && isset($_POST['rekenings']) && is_array($_POST['rekenings'])) {
            $estimatedVars = count($_POST['rekenings']) * 2 + 3; // 2 fields per rekening + 3 common fields
            if ($estimatedVars > $maxInputVars) {
                error_log("Warning: Estimated input vars ({$estimatedVars}) exceeds max_input_vars ({$maxInputVars})");
            }
        }
        
        // Validate common fields
        $errors = [];
        
        if (empty($_POST['program_id']) || !is_numeric($_POST['program_id'])) {
            $errors['program_id'] = 'Program wajib dipilih';
        }
        if (empty($_POST['kegiatan_id']) || !is_numeric($_POST['kegiatan_id'])) {
            $errors['kegiatan_id'] = 'Kegiatan wajib dipilih';
        }
        if (empty($_POST['sub_kegiatan_id']) || !is_numeric($_POST['sub_kegiatan_id'])) {
            $errors['sub_kegiatan_id'] = 'Sub kegiatan wajib dipilih';
        }
        
        // Validate rekenings array
        if (empty($_POST['rekenings']) || !is_array($_POST['rekenings'])) {
            $errors['rekenings'] = 'Minimal harus ada 1 rekening';
        } else {
            $subKegiatanId = (int) $_POST['sub_kegiatan_id'];
            // Debug: log jumlah rekening yang diterima
            error_log('Jumlah rekenings diterima: ' . count($_POST['rekenings']));
            error_log('Rekenings keys: ' . implode(', ', array_keys($_POST['rekenings'])));
            
            $validCount = 0;
            foreach ($_POST['rekenings'] as $index => $rekening) {
                // Skip if not an array
                if (!is_array($rekening)) {
                    continue;
                }
                
                // Skip empty rows (in case there are empty rows in the array)
                if (empty($rekening) || (!isset($rekening['kode_rekening']) && !isset($rekening['nama_rekening']))) {
                    continue;
                }
                
                $kodeRekening = trim($rekening['kode_rekening'] ?? '');
                $namaRekening = trim($rekening['nama_rekening'] ?? '');
                
                if (empty($kodeRekening)) {
                    $errors['rekenings'][$index]['kode_rekening'] = 'Kode rekening wajib diisi';
                } elseif (strlen($kodeRekening) > 50) {
                    $errors['rekenings'][$index]['kode_rekening'] = 'Kode rekening maksimal 50 karakter';
                }
                // Note: kodeExists check moved to save loop to avoid stopping validation early
                
                if (empty($namaRekening)) {
                    $errors['rekenings'][$index]['nama_rekening'] = 'Nama rekening wajib diisi';
                } elseif (strlen($namaRekening) > 255) {
                    $errors['rekenings'][$index]['nama_rekening'] = 'Nama rekening maksimal 255 karakter';
                }
                
                // Count valid rekenings
                if (!empty($kodeRekening) && !empty($namaRekening)) {
                    $validCount++;
                }
            }
            
            if ($validCount === 0) {
                $errors['rekenings'] = 'Minimal harus ada 1 rekening yang valid';
            }
        }
        
        if (!empty($errors)) {
            try {
                $programs = $this->programModel->getAll();
                $this->showBatchFormWithErrors($errors, $_POST, $programs);
            } catch (\Exception $e) {
                $this->handleError('Failed to load form: ' . $e->getMessage());
            }
            return;
        }
        
        try {
            $subKegiatanId = (int) $_POST['sub_kegiatan_id'];
            $successCount = 0;
            $errorMessages = [];
            
            // Debug: log all received data
            error_log('=== BEFORE FILTER ===');
            error_log('Total rekenings received: ' . (isset($_POST['rekenings']) ? count($_POST['rekenings']) : 0));
            if (isset($_POST['rekenings'])) {
                error_log('Rekenings keys: ' . implode(', ', array_keys($_POST['rekenings'])));
                // Log each rekening detail
                foreach ($_POST['rekenings'] as $idx => $rek) {
                    if (is_array($rek)) {
                        error_log("  Rekening[{$idx}]: kode=" . ($rek['kode_rekening'] ?? 'NULL') . ", nama=" . ($rek['nama_rekening'] ?? 'NULL'));
                    } else {
                        error_log("  Rekening[{$idx}]: NOT ARRAY - " . gettype($rek));
                    }
                }
            }
            
            // Filter out empty rows and re-index array to ensure sequential keys
            $rekeningsToSave = array_values(array_filter($_POST['rekenings'], function($r) {
                if (!is_array($r)) {
                    return false;
                }
                $kode = trim($r['kode_rekening'] ?? '');
                $nama = trim($r['nama_rekening'] ?? '');
                return !empty($kode) && !empty($nama);
            }));
            
            error_log('=== AFTER FILTER ===');
            error_log('Jumlah rekenings yang akan disimpan: ' . count($rekeningsToSave));
            foreach ($rekeningsToSave as $idx => $rek) {
                error_log("  ToSave[{$idx}]: kode=" . ($rek['kode_rekening'] ?? 'NULL') . ", nama=" . ($rek['nama_rekening'] ?? 'NULL'));
            }
            
            foreach ($rekeningsToSave as $rekening) {
                try {
                    $kodeRekening = trim($rekening['kode_rekening']);
                    $namaRekening = trim($rekening['nama_rekening']);
                    
                    // Skip if empty (shouldn't happen after filter, but just in case)
                    if (empty($kodeRekening) || empty($namaRekening)) {
                        continue;
                    }
                    
                    // Double check kode exists (race condition protection)
                    if ($this->rekeningModel->kodeExists($subKegiatanId, $kodeRekening)) {
                        $errorMessages[] = "Kode rekening '{$kodeRekening}' sudah digunakan";
                        continue;
                    }
                    
                    $this->rekeningModel->create($subKegiatanId, $kodeRekening, $namaRekening);
                    $successCount++;
                } catch (\Exception $e) {
                    $errorMessages[] = "Error pada kode '{$rekening['kode_rekening']}': " . $e->getMessage();
                    error_log('Error creating rekening: ' . $e->getMessage());
                }
            }
            
            if ($successCount > 0) {
                $message = "Berhasil menambahkan {$successCount} rekening";
                if (!empty($errorMessages)) {
                    $message .= ". " . count($errorMessages) . " rekening gagal: " . implode(', ', array_slice($errorMessages, 0, 3));
                }
                $this->redirectWithMessage(base_url('rekening'), 'success', $message);
            } else {
                $errorMsg = 'Gagal menambahkan rekening';
                if (!empty($errorMessages)) {
                    $errorMsg .= ': ' . implode(', ', array_slice($errorMessages, 0, 5));
                }
                // Check if it might be max_input_vars issue
                $maxInputVars = ini_get('max_input_vars');
                if ($maxInputVars && count($rekeningsToSave) > ($maxInputVars / 3)) {
                    $errorMsg .= ". Perhatian: Mungkin ada batasan max_input_vars ({$maxInputVars}). Silakan hubungi administrator.";
                }
                $this->redirectWithMessage(base_url('rekening'), 'error', $errorMsg);
            }
        } catch (\Exception $e) {
            $this->handleError('Gagal menambahkan rekening: ' . $e->getMessage());
        }
    }

    /**
     * Show form for editing account
     */
    public function edit(int $id): void {
        try {
            $rekening = $this->rekeningModel->getById($id);
            
            if (!$rekening) {
                $this->redirectWithMessage(base_url('rekening'), 'error', 'Rekening tidak ditemukan');
                return;
            }
            
            // Set hierarchy IDs for form
            $rekening['program_id'] = $rekening['program_id'] ?? '';
            $rekening['kegiatan_id'] = $rekening['kegiatan_id'] ?? '';
            $rekening['sub_kegiatan_id'] = $rekening['sub_kegiatan_id'] ?? '';
            
            $programs = $this->programModel->getAll();
            $kegiatans = $this->kegiatanModel->getByProgramId($rekening['program_id'] ?? 0);
            $subKegiatans = $this->subKegiatanModel->getByKegiatanId($rekening['kegiatan_id'] ?? 0);
            
            $pageTitle = 'Edit Rekening';
            $activePage = 'rekening';
            $viewFile = __DIR__ . '/../../views/rekening/form.php';
            $action = 'update';
            
            include __DIR__ . '/../../views/layout.php';
        } catch (\Exception $e) {
            $this->handleError('Gagal memuat rekening: ' . $e->getMessage());
        }
    }

    /**
     * Update account
     */
    public function update(int $id): void {
        $errors = $this->validate($_POST, $id);
        
        if (!empty($errors)) {
            try {
                $programs = $this->programModel->getAll();
                $rekening = array_merge(['id' => $id], $_POST);
                $this->showFormWithErrors($errors, $rekening, $programs);
            } catch (\Exception $e) {
                $this->handleError('Failed to load form: ' . $e->getMessage());
            }
            return;
        }
        
        try {
            $this->rekeningModel->update(
                $id,
                (int) $_POST['sub_kegiatan_id'],
                trim($_POST['kode_rekening']),
                trim($_POST['nama_rekening'])
            );
            
            $this->redirectWithMessage(base_url('rekening'), 'success', 'Rekening berhasil diperbarui');
        } catch (\Exception $e) {
            $this->handleError('Gagal memperbarui rekening: ' . $e->getMessage());
        }
    }

    /**
     * Delete account
     */
    public function delete(int $id): void {
        try {
            $this->rekeningModel->delete($id);
            $this->redirectWithMessage(base_url('rekening'), 'success', 'Rekening berhasil dihapus');
        } catch (\Exception $e) {
            $this->redirectWithMessage(base_url('rekening'), 'error', 'Gagal menghapus rekening: ' . $e->getMessage());
        }
    }

    /**
     * Validate form data
     *
     * @param array $data
     * @param int|null $excludeId
     * @return array
     */
    private function validate(array $data, ?int $excludeId = null): array {
        $errors = [];

        // program, kegiatan, sub_kegiatan dishare dengan cascading, tapi yang disimpan hanya sub_kegiatan_id
        if (empty($data['program_id']) || !is_numeric($data['program_id'])) {
            $errors['program_id'] = 'Program wajib dipilih';
        }
        if (empty($data['kegiatan_id']) || !is_numeric($data['kegiatan_id'])) {
            $errors['kegiatan_id'] = 'Kegiatan wajib dipilih';
        }
        if (empty($data['sub_kegiatan_id']) || !is_numeric($data['sub_kegiatan_id'])) {
            $errors['sub_kegiatan_id'] = 'Sub kegiatan wajib dipilih';
        }

        // kode_rekening - harus unik per sub_kegiatan_id
        if (empty($data['kode_rekening'])) {
            $errors['kode_rekening'] = 'Kode rekening wajib diisi';
        } elseif (strlen(trim($data['kode_rekening'])) > 50) {
            $errors['kode_rekening'] = 'Kode rekening maksimal 50 karakter';
        } elseif (!empty($data['sub_kegiatan_id']) && is_numeric($data['sub_kegiatan_id'])) {
            // Cek apakah kode rekening sudah ada di sub kegiatan yang sama
            if ($this->rekeningModel->kodeExists((int) $data['sub_kegiatan_id'], trim($data['kode_rekening']), $excludeId)) {
                $errors['kode_rekening'] = 'Kode rekening sudah digunakan di sub kegiatan ini';
            }
        }

        // nama_rekening
        if (empty($data['nama_rekening'])) {
            $errors['nama_rekening'] = 'Nama rekening wajib diisi';
        } elseif (strlen(trim($data['nama_rekening'])) > 255) {
            $errors['nama_rekening'] = 'Nama rekening maksimal 255 karakter';
        }

        return $errors;
    }

    /**
     * Show form with validation errors
     */
    private function showFormWithErrors(array $errors, array $data, array $programs): void {
        $pageTitle = isset($data['id']) ? 'Edit Rekening' : 'Tambah Rekening';
        $activePage = 'rekening';
        $viewFile = __DIR__ . '/../../views/rekening/form.php';
        $rekening = $data;
        $action = isset($data['id']) ? 'update' : 'store';
        $validationErrors = $errors;

        include __DIR__ . '/../../views/layout.php';
    }
    
    /**
     * Show batch form with validation errors
     */
    private function showBatchFormWithErrors(array $errors, array $data, array $programs): void {
        try {
            $pageTitle = 'Tambah Rekening';
            $activePage = 'rekening';
            $viewFile = __DIR__ . '/../../views/rekening/form.php';
            $rekening = null;
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
    private function redirectWithMessage(string $url, string $type, string $message): void {
        $_SESSION['flash_message'] = $message;
        $_SESSION['flash_type'] = $type;
        header('Location: ' . $url);
        exit;
    }

    /**
     * Handle errors
     */
    private function handleError(string $message): void {
        error_log($message);
        $this->redirectWithMessage(base_url('rekening'), 'error', 'Terjadi kesalahan sistem');
    }
}


