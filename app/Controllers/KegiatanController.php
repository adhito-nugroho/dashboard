<?php

namespace App\Controllers;

use App\Models\Kegiatan;
use App\Models\Program;
use PDOException;

class KegiatanController {
    private Kegiatan $kegiatanModel;
    private Program $programModel;
    
    public function __construct(Kegiatan $kegiatanModel, Program $programModel) {
        $this->kegiatanModel = $kegiatanModel;
        $this->programModel = $programModel;
    }
    
    /**
     * Display list of activities
     */
    public function index(): void {
        try {
            $kegiatans = $this->kegiatanModel->getAll();

            $perPage = 10;
            $page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
            $total = count($kegiatans);
            $totalPages = max(1, (int) ceil($total / $perPage));
            if ($page > $totalPages) {
                $page = $totalPages;
            }
            $offset = ($page - 1) * $perPage;
            $kegiatans = array_slice($kegiatans, $offset, $perPage);
            $pagination = [
                'page' => $page,
                'perPage' => $perPage,
                'total' => $total,
                'totalPages' => $totalPages,
                'baseUrl' => base_url('kegiatan')
            ];
            
            $pageTitle = 'Kegiatan';
            $activePage = 'kegiatan';
            $viewFile = __DIR__ . '/../../views/kegiatan/index.php';
            
            include __DIR__ . '/../../views/layout.php';
        } catch (\Exception $e) {
            $this->handleError('Failed to load activities: ' . $e->getMessage());
        }
    }
    
    /**
     * Show form for creating new activity
     */
    public function create(): void {
        try {
            $programs = $this->programModel->getAll();
            
            $pageTitle = 'Tambah Kegiatan';
            $activePage = 'kegiatan';
            $viewFile = __DIR__ . '/../../views/kegiatan/form.php';
            $kegiatan = null; // New record
            $action = 'store';
            
            include __DIR__ . '/../../views/layout.php';
        } catch (\Exception $e) {
            $this->handleError('Failed to load form: ' . $e->getMessage());
        }
    }
    
    /**
     * Store new activity
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
            $this->kegiatanModel->create(
                (int) $_POST['program_id'],
                trim($_POST['kode_kegiatan']),
                trim($_POST['nama_kegiatan'])
            );
            
            $this->redirectWithMessage(base_url('kegiatan'), 'success', 'Kegiatan berhasil ditambahkan');
        } catch (\Exception $e) {
            $this->handleError('Gagal menambahkan kegiatan: ' . $e->getMessage());
        }
    }
    
    /**
     * Show form for editing activity
     */
    public function edit(int $id): void {
        try {
            $kegiatan = $this->kegiatanModel->getById($id);
            
            if (!$kegiatan) {
                $this->redirectWithMessage(base_url('kegiatan'), 'error', 'Kegiatan tidak ditemukan');
                return;
            }
            
            $programs = $this->programModel->getAll();
            
            $pageTitle = 'Edit Kegiatan';
            $activePage = 'kegiatan';
            $viewFile = __DIR__ . '/../../views/kegiatan/form.php';
            $action = 'update';
            
            include __DIR__ . '/../../views/layout.php';
        } catch (\Exception $e) {
            $this->handleError('Gagal memuat kegiatan: ' . $e->getMessage());
        }
    }
    
    /**
     * Update activity
     */
    public function update(int $id): void {
        $errors = $this->validate($_POST, $id);
        
        if (!empty($errors)) {
            try {
                $programs = $this->programModel->getAll();
                $kegiatan = array_merge(['id' => $id], $_POST);
                $this->showFormWithErrors($errors, $kegiatan, $programs);
            } catch (\Exception $e) {
                $this->handleError('Failed to load form: ' . $e->getMessage());
            }
            return;
        }
        
        try {
            $this->kegiatanModel->update(
                $id,
                (int) $_POST['program_id'],
                trim($_POST['kode_kegiatan']),
                trim($_POST['nama_kegiatan'])
            );
            
            $this->redirectWithMessage(base_url('kegiatan'), 'success', 'Kegiatan berhasil diperbarui');
        } catch (\Exception $e) {
            $this->handleError('Gagal memperbarui kegiatan: ' . $e->getMessage());
        }
    }
    
    /**
     * Delete activity
     */
    public function delete(int $id): void {
        try {
            $this->kegiatanModel->delete($id);
            $this->redirectWithMessage(base_url('kegiatan'), 'success', 'Kegiatan berhasil dihapus');
        } catch (\Exception $e) {
            $this->redirectWithMessage(base_url('kegiatan'), 'error', 'Gagal menghapus kegiatan: ' . $e->getMessage());
        }
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
        
        // Validate program_id
        if (empty($data['program_id'])) {
            $errors['program_id'] = 'Program wajib dipilih';
        } elseif (!is_numeric($data['program_id'])) {
            $errors['program_id'] = 'Program tidak valid';
        }
        
        // Validate kode_kegiatan
        if (empty($data['kode_kegiatan'])) {
            $errors['kode_kegiatan'] = 'Kode kegiatan wajib diisi';
        } elseif (strlen(trim($data['kode_kegiatan'])) > 50) {
            $errors['kode_kegiatan'] = 'Kode kegiatan maksimal 50 karakter';
        } elseif ($this->kegiatanModel->kodeExists(trim($data['kode_kegiatan']), $excludeId)) {
            $errors['kode_kegiatan'] = 'Kode kegiatan sudah digunakan';
        }
        
        // Validate nama_kegiatan
        if (empty($data['nama_kegiatan'])) {
            $errors['nama_kegiatan'] = 'Nama kegiatan wajib diisi';
        } elseif (strlen(trim($data['nama_kegiatan'])) > 255) {
            $errors['nama_kegiatan'] = 'Nama kegiatan maksimal 255 karakter';
        }
        
        return $errors;
    }
    
    /**
     * Show form with validation errors
     */
    private function showFormWithErrors(array $errors, array $data, array $programs): void {
        $pageTitle = isset($data['id']) ? 'Edit Kegiatan' : 'Tambah Kegiatan';
        $activePage = 'kegiatan';
        $viewFile = __DIR__ . '/../../views/kegiatan/form.php';
        $kegiatan = $data;
        $action = isset($data['id']) ? 'update' : 'store';
        $validationErrors = $errors;
        
        include __DIR__ . '/../../views/layout.php';
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
        $this->redirectWithMessage(base_url('kegiatan'), 'error', 'Terjadi kesalahan sistem');
    }
}

