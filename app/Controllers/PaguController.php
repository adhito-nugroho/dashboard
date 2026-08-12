<?php

namespace App\Controllers;

use App\Models\Pagu;
use App\Models\Program;
use App\Models\Kegiatan;
use App\Models\SubKegiatan;
use App\Models\Rekening;
use PDOException;

class PaguController {
    private Pagu $paguModel;
    private Program $programModel;
    private Kegiatan $kegiatanModel;
    private SubKegiatan $subKegiatanModel;
    private Rekening $rekeningModel;
    
    public function __construct(
        Pagu $paguModel,
        Program $programModel,
        Kegiatan $kegiatanModel,
        SubKegiatan $subKegiatanModel,
        Rekening $rekeningModel
    ) {
        $this->paguModel = $paguModel;
        $this->programModel = $programModel;
        $this->kegiatanModel = $kegiatanModel;
        $this->subKegiatanModel = $subKegiatanModel;
        $this->rekeningModel = $rekeningModel;
    }
    
    /**
     * Display list of budget allocations
     */
    public function index(): void {
        try {
            $pagus = $this->paguModel->getAll();
            
            $perPage = 10;
            $page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
            $total = count($pagus);
            $totalPages = max(1, (int) ceil($total / $perPage));
            if ($page > $totalPages) {
                $page = $totalPages;
            }
            $offset = ($page - 1) * $perPage;
            $pagus = array_slice($pagus, $offset, $perPage);
            $pagination = [
                'page' => $page,
                'perPage' => $perPage,
                'total' => $total,
                'totalPages' => $totalPages,
                'baseUrl' => base_url('pagu')
            ];
            
            $pageTitle = 'Pagu';
            $activePage = 'pagu';
            $viewFile = __DIR__ . '/../../views/pagu/index.php';
            
            include __DIR__ . '/../../views/layout.php';
        } catch (\Exception $e) {
            $this->handleError('Failed to load budget allocations: ' . $e->getMessage());
        }
    }
    
    /**
     * Show form for creating new budget allocation
     */
    public function create(): void {
        try {
            $programs = $this->programModel->getAll();
            
            $pageTitle = 'Tambah Pagu';
            $activePage = 'pagu';
            $viewFile = __DIR__ . '/../../views/pagu/form.php';
            $pagu = null; // New record
            $action = 'store';
            
            include __DIR__ . '/../../views/layout.php';
        } catch (\Exception $e) {
            $this->handleError('Failed to load form: ' . $e->getMessage());
        }
    }
    
    /**
     * Store new budget allocation
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
            $this->paguModel->create(
                (int) $_POST['rekening_id'],
                (int) $_POST['tahun'],
                (float) str_replace(',', '.', str_replace('.', '', $_POST['nilai_pagu']))
            );
            
            $this->redirectWithMessage(base_url('pagu'), 'success', 'Pagu berhasil ditambahkan');
        } catch (\Exception $e) {
            $this->handleError('Gagal menambahkan pagu: ' . $e->getMessage());
        }
    }
    
    /**
     * Store multiple budget allocations in batch
     */
    public function storeBatch(): void {
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
        
        // Validate tahun
        if (empty($_POST['tahun'])) {
            $errors['tahun'] = 'Tahun wajib diisi';
        } elseif (!is_numeric($_POST['tahun'])) {
            $errors['tahun'] = 'Tahun harus berupa angka';
        } else {
            $tahun = (int) $_POST['tahun'];
            if ($tahun < 2000 || $tahun > 2100) {
                $errors['tahun'] = 'Tahun harus antara 2000 dan 2100';
            }
        }
        
        // Validate pagus array
        if (empty($_POST['pagus']) || !is_array($_POST['pagus'])) {
            $errors['pagus'] = 'Minimal harus ada 1 pagu';
        } else {
            $tahun = (int) $_POST['tahun'];
            $validCount = 0;
            
            foreach ($_POST['pagus'] as $index => $pagu) {
                // Skip if not an array
                if (!is_array($pagu)) {
                    continue;
                }
                
                // Skip empty rows
                if (empty($pagu) || (!isset($pagu['rekening_id']) && !isset($pagu['nilai_pagu']))) {
                    continue;
                }
                
                $rekeningId = trim($pagu['rekening_id'] ?? '');
                $nilaiPagu = str_replace(',', '.', str_replace('.', '', $pagu['nilai_pagu'] ?? ''));
                
                if (empty($rekeningId)) {
                    $errors['pagus'][$index]['rekening_id'] = 'Rekening wajib dipilih';
                } elseif (!is_numeric($rekeningId)) {
                    $errors['pagus'][$index]['rekening_id'] = 'Rekening tidak valid';
                } else {
                    // Check for duplicate pagu (rekening_id + tahun)
                    if ($this->paguModel->exists((int) $rekeningId, $tahun)) {
                        $errors['pagus'][$index]['rekening_id'] = 'Pagu untuk rekening dan tahun ini sudah ada';
                    }
                }
                
                if (empty($nilaiPagu)) {
                    $errors['pagus'][$index]['nilai_pagu'] = 'Nilai pagu wajib diisi';
                } elseif (!is_numeric($nilaiPagu)) {
                    $errors['pagus'][$index]['nilai_pagu'] = 'Nilai pagu harus berupa angka';
                } elseif ((float) $nilaiPagu < 0) {
                    $errors['pagus'][$index]['nilai_pagu'] = 'Nilai pagu tidak boleh negatif';
                }
                
                // Count valid pagus
                if (!empty($rekeningId) && !empty($nilaiPagu) && is_numeric($nilaiPagu) && (float) $nilaiPagu > 0) {
                    $validCount++;
                }
            }
            
            if ($validCount === 0) {
                $errors['pagus'] = 'Minimal harus ada 1 pagu yang valid';
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
            $tahun = (int) $_POST['tahun'];
            $successCount = 0;
            $errorMessages = [];
            
            // Filter out empty rows and re-index array
            $pagusToSave = array_values(array_filter($_POST['pagus'], function($p) {
                if (!is_array($p)) {
                    return false;
                }
                $rekeningId = trim($p['rekening_id'] ?? '');
                $nilaiPagu = str_replace(',', '.', str_replace('.', '', $p['nilai_pagu'] ?? ''));
                return !empty($rekeningId) && !empty($nilaiPagu) && is_numeric($nilaiPagu) && (float) $nilaiPagu > 0;
            }));
            
            foreach ($pagusToSave as $pagu) {
                try {
                    $rekeningId = (int) $pagu['rekening_id'];
                    $nilaiPagu = (float) str_replace(',', '.', str_replace('.', '', $pagu['nilai_pagu']));
                    
                    // Double check pagu exists (race condition protection)
                    if ($this->paguModel->exists($rekeningId, $tahun)) {
                        $errorMessages[] = "Pagu untuk rekening ID {$rekeningId} dan tahun {$tahun} sudah ada";
                        continue;
                    }
                    
                    $this->paguModel->create($rekeningId, $tahun, $nilaiPagu);
                    $successCount++;
                } catch (\Exception $e) {
                    $errorMessages[] = "Error pada rekening ID {$pagu['rekening_id']}: " . $e->getMessage();
                }
            }
            
            if ($successCount > 0) {
                $message = "Berhasil menambahkan {$successCount} pagu";
                if (!empty($errorMessages)) {
                    $message .= ". " . count($errorMessages) . " pagu gagal: " . implode(', ', array_slice($errorMessages, 0, 3));
                }
                $this->redirectWithMessage(base_url('pagu'), 'success', $message);
            } else {
                $errorMsg = 'Gagal menambahkan pagu';
                if (!empty($errorMessages)) {
                    $errorMsg .= ': ' . implode(', ', array_slice($errorMessages, 0, 5));
                }
                $this->redirectWithMessage(base_url('pagu'), 'error', $errorMsg);
            }
        } catch (\Exception $e) {
            $this->handleError('Gagal menambahkan pagu: ' . $e->getMessage());
        }
    }
    
    /**
     * Show form for editing budget allocation
     */
    public function edit(int $id): void {
        try {
            $pagu = $this->paguModel->getById($id);
            
            if (!$pagu) {
                $this->redirectWithMessage(base_url('pagu'), 'error', 'Pagu tidak ditemukan');
                return;
            }
            
            // Get rekening to find its hierarchy
            $rekening = $this->rekeningModel->getById($pagu['rekening_id']);
            
            // Merge pagu with hierarchy IDs for form
            $pagu['program_id'] = $rekening['program_id'] ?? '';
            $pagu['kegiatan_id'] = $rekening['kegiatan_id'] ?? '';
            $pagu['sub_kegiatan_id'] = $rekening['sub_kegiatan_id'] ?? '';
            
            // Get all related data for dropdowns
            $programs = $this->programModel->getAll();
            $kegiatans = $this->kegiatanModel->getByProgramId($rekening['program_id'] ?? 0);
            $subKegiatans = $this->subKegiatanModel->getByKegiatanId($rekening['kegiatan_id'] ?? 0);
            $rekenings = $this->rekeningModel->getBySubKegiatanId($rekening['sub_kegiatan_id'] ?? 0);
            
            $pageTitle = 'Edit Pagu';
            $activePage = 'pagu';
            $viewFile = __DIR__ . '/../../views/pagu/form.php';
            $action = 'update';
            
            include __DIR__ . '/../../views/layout.php';
        } catch (\Exception $e) {
            $this->handleError('Gagal memuat pagu: ' . $e->getMessage());
        }
    }
    
    /**
     * Update budget allocation
     */
    public function update(int $id): void {
        $errors = $this->validate($_POST, $id);
        
        if (!empty($errors)) {
            try {
                $programs = $this->programModel->getAll();
                $pagu = array_merge(['id' => $id], $_POST);
                $this->showFormWithErrors($errors, $pagu, $programs);
            } catch (\Exception $e) {
                $this->handleError('Failed to load form: ' . $e->getMessage());
            }
            return;
        }
        
        try {
            $this->paguModel->update(
                $id,
                (int) $_POST['rekening_id'],
                (int) $_POST['tahun'],
                (float) str_replace(',', '.', str_replace('.', '', $_POST['nilai_pagu']))
            );
            
            $this->redirectWithMessage(base_url('pagu'), 'success', 'Pagu berhasil diperbarui');
        } catch (\Exception $e) {
            $this->handleError('Gagal memperbarui pagu: ' . $e->getMessage());
        }
    }
    
    /**
     * Delete budget allocation
     */
    public function delete(int $id): void {
        try {
            $this->paguModel->delete($id);
            $this->redirectWithMessage(base_url('pagu'), 'success', 'Pagu berhasil dihapus');
        } catch (\Exception $e) {
            $this->redirectWithMessage(base_url('pagu'), 'error', 'Gagal menghapus pagu: ' . $e->getMessage());
        }
    }
    
    /**
     * AJAX: Get activities by program
     */
    public function getKegiatansByProgram(): void {
        header('Content-Type: application/json');
        try {
            $programId = (int) ($_GET['program_id'] ?? 0);
            $kegiatans = $this->kegiatanModel->getByProgramId($programId);
            echo json_encode($kegiatans);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit;
    }
    
    /**
     * AJAX: Get sub-activities by activity
     */
    public function getSubKegiatansByKegiatan(): void {
        header('Content-Type: application/json');
        try {
            $kegiatanId = (int) ($_GET['kegiatan_id'] ?? 0);
            $subKegiatans = $this->subKegiatanModel->getByKegiatanId($kegiatanId);
            echo json_encode($subKegiatans);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit;
    }
    
    /**
     * AJAX: Get accounts by sub-activity
     */
    public function getRekeningsBySubKegiatan(): void {
        header('Content-Type: application/json');
        try {
            $subKegiatanId = (int) ($_GET['sub_kegiatan_id'] ?? 0);
            $rekenings = $this->rekeningModel->getBySubKegiatanId($subKegiatanId);
            echo json_encode($rekenings);
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
    private function validate(array $data, ?int $excludeId = null): array {
        $errors = [];
        
        // Validate rekening_id
        if (empty($data['rekening_id'])) {
            $errors['rekening_id'] = 'Rekening wajib dipilih';
        } elseif (!is_numeric($data['rekening_id'])) {
            $errors['rekening_id'] = 'Rekening tidak valid';
        }
        
        // Validate tahun
        if (empty($data['tahun'])) {
            $errors['tahun'] = 'Tahun wajib diisi';
        } elseif (!is_numeric($data['tahun'])) {
            $errors['tahun'] = 'Tahun harus berupa angka';
        } else {
            $tahun = (int) $data['tahun'];
            if ($tahun < 2000 || $tahun > 2100) {
                $errors['tahun'] = 'Tahun harus antara 2000 dan 2100';
            }
        }
        
        // Validate nilai_pagu
        if (empty($data['nilai_pagu'])) {
            $errors['nilai_pagu'] = 'Nilai pagu wajib diisi';
        } else {
            $nilaiPagu = str_replace(',', '.', str_replace('.', '', $data['nilai_pagu']));
            if (!is_numeric($nilaiPagu)) {
                $errors['nilai_pagu'] = 'Nilai pagu harus berupa angka';
            } elseif ((float) $nilaiPagu < 0) {
                $errors['nilai_pagu'] = 'Nilai pagu tidak boleh negatif';
            }
        }
        
        // Check for duplicate pagu (rekening_id + tahun)
        if (empty($errors['rekening_id']) && empty($errors['tahun'])) {
            $rekeningId = (int) $data['rekening_id'];
            $tahun = (int) $data['tahun'];
            if ($this->paguModel->exists($rekeningId, $tahun, $excludeId)) {
                $errors['rekening_id'] = 'Pagu untuk rekening dan tahun ini sudah ada';
            }
        }
        
        return $errors;
    }
    
    /**
     * Show form with validation errors
     */
    private function showFormWithErrors(array $errors, array $data, array $programs): void {
        $pageTitle = isset($data['id']) ? 'Edit Pagu' : 'Tambah Pagu';
        $activePage = 'pagu';
        $viewFile = __DIR__ . '/../../views/pagu/form.php';
        $pagu = $data;
        $action = isset($data['id']) ? 'update' : 'store';
        $validationErrors = $errors;
        
        include __DIR__ . '/../../views/layout.php';
    }
    
    /**
     * Show batch form with validation errors
     */
    private function showBatchFormWithErrors(array $errors, array $data, array $programs): void {
        try {
            $pageTitle = 'Tambah Pagu';
            $activePage = 'pagu';
            $viewFile = __DIR__ . '/../../views/pagu/form.php';
            $pagu = null;
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
        $this->redirectWithMessage(base_url('pagu'), 'error', 'Terjadi kesalahan sistem');
    }
}

