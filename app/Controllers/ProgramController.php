<?php

namespace App\Controllers;

use App\Models\Program;
use PDOException;

class ProgramController {
    private Program $programModel;
    
    public function __construct(Program $programModel) {
        $this->programModel = $programModel;
    }
    
    /**
     * Display list of programs
     */
    public function index(): void {
        try {
            $programs = $this->programModel->getAll();

            // Simple pagination (array slice)
            $perPage = 10;
            $page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
            $total = count($programs);
            $totalPages = max(1, (int) ceil($total / $perPage));
            if ($page > $totalPages) {
                $page = $totalPages;
            }
            $offset = ($page - 1) * $perPage;
            $programs = array_slice($programs, $offset, $perPage);
            $pagination = [
                'page' => $page,
                'perPage' => $perPage,
                'total' => $total,
                'totalPages' => $totalPages,
                'baseUrl' => base_url('program')
            ];
            
            $pageTitle = 'Program';
            $activePage = 'program';
            $viewFile = __DIR__ . '/../../views/program/index.php';
            
            include __DIR__ . '/../../views/layout.php';
        } catch (\Exception $e) {
            $this->handleError('Failed to load programs: ' . $e->getMessage());
        }
    }
    
    /**
     * Show form for creating new program
     */
    public function create(): void {
        $pageTitle = 'Tambah Program';
        $activePage = 'program';
        $viewFile = __DIR__ . '/../../views/program/form.php';
        $program = null; // New record
        $action = 'store';
        
        include __DIR__ . '/../../views/layout.php';
    }
    
    /**
     * Store new program
     */
    public function store(): void {
        $errors = $this->validate($_POST);
        
        if (!empty($errors)) {
            $this->showFormWithErrors($errors, $_POST);
            return;
        }
        
        try {
            $this->programModel->create(
                trim($_POST['kode_program']),
                trim($_POST['nama_program']),
                (int) $_POST['tahun']
            );
            
            $this->redirectWithMessage(base_url('program'), 'success', 'Program berhasil ditambahkan');
        } catch (\Exception $e) {
            $this->handleError('Gagal menambahkan program: ' . $e->getMessage());
        }
    }
    
    /**
     * Show form for editing program
     */
    public function edit(int $id): void {
        try {
            $program = $this->programModel->getById($id);
            
            if (!$program) {
                $this->redirectWithMessage(base_url('program'), 'error', 'Program tidak ditemukan');
                return;
            }
            
            $pageTitle = 'Edit Program';
            $activePage = 'program';
            $viewFile = __DIR__ . '/../../views/program/form.php';
            $action = 'update';
            
            include __DIR__ . '/../../views/layout.php';
        } catch (\Exception $e) {
            $this->handleError('Gagal memuat program: ' . $e->getMessage());
        }
    }
    
    /**
     * Update program
     */
    public function update(int $id): void {
        $errors = $this->validate($_POST, $id);
        
        if (!empty($errors)) {
            $program = array_merge(['id' => $id], $_POST);
            $this->showFormWithErrors($errors, $program);
            return;
        }
        
        try {
            $this->programModel->update(
                $id,
                trim($_POST['kode_program']),
                trim($_POST['nama_program']),
                (int) $_POST['tahun']
            );
            
            $this->redirectWithMessage(base_url('program'), 'success', 'Program berhasil diperbarui');
        } catch (\Exception $e) {
            $this->handleError('Gagal memperbarui program: ' . $e->getMessage());
        }
    }
    
    /**
     * Delete program
     */
    public function delete(int $id): void {
        try {
            $this->programModel->delete($id);
            $this->redirectWithMessage(base_url('program'), 'success', 'Program berhasil dihapus');
        } catch (\Exception $e) {
            $this->redirectWithMessage(base_url('program'), 'error', 'Gagal menghapus program: ' . $e->getMessage());
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
        
        // Validate kode_program
        if (empty($data['kode_program'])) {
            $errors['kode_program'] = 'Kode program wajib diisi';
        } elseif (strlen(trim($data['kode_program'])) > 50) {
            $errors['kode_program'] = 'Kode program maksimal 50 karakter';
        } elseif ($this->programModel->kodeExists(trim($data['kode_program']), $excludeId)) {
            $errors['kode_program'] = 'Kode program sudah digunakan';
        }
        
        // Validate nama_program
        if (empty($data['nama_program'])) {
            $errors['nama_program'] = 'Nama program wajib diisi';
        } elseif (strlen(trim($data['nama_program'])) > 255) {
            $errors['nama_program'] = 'Nama program maksimal 255 karakter';
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
        
        return $errors;
    }
    
    /**
     * Show form with validation errors
     */
    private function showFormWithErrors(array $errors, array $data): void {
        $pageTitle = isset($data['id']) ? 'Edit Program' : 'Tambah Program';
        $activePage = 'program';
        $viewFile = __DIR__ . '/../../views/program/form.php';
        $program = $data;
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
        $this->redirectWithMessage(base_url('program'), 'error', 'Terjadi kesalahan sistem');
    }
}

