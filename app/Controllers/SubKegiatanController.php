<?php

namespace App\Controllers;

use App\Models\SubKegiatan;
use App\Models\Kegiatan;
use App\Models\Seksi;
use PDOException;

class SubKegiatanController {
    private SubKegiatan $subKegiatanModel;
    private Kegiatan $kegiatanModel;
    private Seksi $seksiModel;
    
    public function __construct(SubKegiatan $subKegiatanModel, Kegiatan $kegiatanModel, Seksi $seksiModel) {
        $this->subKegiatanModel = $subKegiatanModel;
        $this->kegiatanModel = $kegiatanModel;
        $this->seksiModel = $seksiModel;
    }
    
    /**
     * Display list of sub-activities
     */
    public function index(): void {
        try {
            $subKegiatans = $this->subKegiatanModel->getAll();
            
            $perPage = 10;
            $page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
            $total = count($subKegiatans);
            $totalPages = max(1, (int) ceil($total / $perPage));
            if ($page > $totalPages) {
                $page = $totalPages;
            }
            $offset = ($page - 1) * $perPage;
            $subKegiatans = array_slice($subKegiatans, $offset, $perPage);
            $pagination = [
                'page' => $page,
                'perPage' => $perPage,
                'total' => $total,
                'totalPages' => $totalPages,
                'baseUrl' => base_url('sub-kegiatan')
            ];
            
            $pageTitle = 'Sub Kegiatan';
            $activePage = 'sub_kegiatan';
            $viewFile = __DIR__ . '/../../views/sub_kegiatan/index.php';
            
            include __DIR__ . '/../../views/layout.php';
        } catch (\Exception $e) {
            $this->handleError('Failed to load sub-activities: ' . $e->getMessage());
        }
    }
    
    /**
     * Show form for creating new sub-activity
     */
    public function create(): void {
        try {
            $kegiatans = $this->kegiatanModel->getAll();
            $seksis = $this->seksiModel->getAll();
            
            $pageTitle = 'Tambah Sub Kegiatan';
            $activePage = 'sub_kegiatan';
            $viewFile = __DIR__ . '/../../views/sub_kegiatan/form.php';
            $subKegiatan = null; // New record
            $action = 'store';
            
            include __DIR__ . '/../../views/layout.php';
        } catch (\Exception $e) {
            $this->handleError('Failed to load form: ' . $e->getMessage());
        }
    }
    
    /**
     * Store new sub-activity
     */
    public function store(): void {
        $errors = $this->validate($_POST);
        
        if (!empty($errors)) {
            try {
                $kegiatans = $this->kegiatanModel->getAll();
                $seksis = $this->seksiModel->getAll();
                $this->showFormWithErrors($errors, $_POST, $kegiatans, $seksis);
            } catch (\Exception $e) {
                $this->handleError('Failed to load form: ' . $e->getMessage());
            }
            return;
        }
        
        try {
            $this->subKegiatanModel->create(
                (int) $_POST['kegiatan_id'],
                (int) $_POST['seksi_id'],
                trim($_POST['kode_sub_kegiatan']),
                trim($_POST['nama_sub_kegiatan'])
            );
            
            $this->redirectWithMessage(base_url('sub-kegiatan'), 'success', 'Sub kegiatan berhasil ditambahkan');
        } catch (\Exception $e) {
            $this->handleError('Gagal menambahkan sub kegiatan: ' . $e->getMessage());
        }
    }
    
    /**
     * Show form for editing sub-activity
     */
    public function edit(int $id): void {
        try {
            $subKegiatan = $this->subKegiatanModel->getById($id);
            
            if (!$subKegiatan) {
                $this->redirectWithMessage(base_url('sub-kegiatan'), 'error', 'Sub kegiatan tidak ditemukan');
                return;
            }
            
            $kegiatans = $this->kegiatanModel->getAll();
            $seksis = $this->seksiModel->getAll();
            
            $pageTitle = 'Edit Sub Kegiatan';
            $activePage = 'sub_kegiatan';
            $viewFile = __DIR__ . '/../../views/sub_kegiatan/form.php';
            $action = 'update';
            
            include __DIR__ . '/../../views/layout.php';
        } catch (\Exception $e) {
            $this->handleError('Gagal memuat sub kegiatan: ' . $e->getMessage());
        }
    }
    
    /**
     * Update sub-activity
     */
    public function update(int $id): void {
        $errors = $this->validate($_POST, $id);
        
        if (!empty($errors)) {
            try {
                $kegiatans = $this->kegiatanModel->getAll();
                $seksis = $this->seksiModel->getAll();
                $subKegiatan = array_merge(['id' => $id], $_POST);
                $this->showFormWithErrors($errors, $subKegiatan, $kegiatans, $seksis);
            } catch (\Exception $e) {
                $this->handleError('Failed to load form: ' . $e->getMessage());
            }
            return;
        }
        
        try {
            $this->subKegiatanModel->update(
                $id,
                (int) $_POST['kegiatan_id'],
                (int) $_POST['seksi_id'],
                trim($_POST['kode_sub_kegiatan']),
                trim($_POST['nama_sub_kegiatan'])
            );
            
            $this->redirectWithMessage(base_url('sub-kegiatan'), 'success', 'Sub kegiatan berhasil diperbarui');
        } catch (\Exception $e) {
            $this->handleError('Gagal memperbarui sub kegiatan: ' . $e->getMessage());
        }
    }
    
    /**
     * Delete sub-activity
     */
    public function delete(int $id): void {
        try {
            $this->subKegiatanModel->delete($id);
            $this->redirectWithMessage(base_url('sub-kegiatan'), 'success', 'Sub kegiatan berhasil dihapus');
        } catch (\Exception $e) {
            $this->redirectWithMessage(base_url('sub-kegiatan'), 'error', 'Gagal menghapus sub kegiatan: ' . $e->getMessage());
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
        
        // Validate kegiatan_id
        if (empty($data['kegiatan_id'])) {
            $errors['kegiatan_id'] = 'Kegiatan wajib dipilih';
        } elseif (!is_numeric($data['kegiatan_id'])) {
            $errors['kegiatan_id'] = 'Kegiatan tidak valid';
        }

        // Validate seksi_id
        if (empty($data['seksi_id'])) {
            $errors['seksi_id'] = 'Seksi wajib dipilih';
        } elseif (!is_numeric($data['seksi_id'])) {
            $errors['seksi_id'] = 'Seksi tidak valid';
        }
        
        // Validate kode_sub_kegiatan
        if (empty($data['kode_sub_kegiatan'])) {
            $errors['kode_sub_kegiatan'] = 'Kode sub kegiatan wajib diisi';
        } elseif (strlen(trim($data['kode_sub_kegiatan'])) > 50) {
            $errors['kode_sub_kegiatan'] = 'Kode sub kegiatan maksimal 50 karakter';
        } elseif ($this->subKegiatanModel->kodeExists(trim($data['kode_sub_kegiatan']), $excludeId)) {
            $errors['kode_sub_kegiatan'] = 'Kode sub kegiatan sudah digunakan';
        }
        
        // Validate nama_sub_kegiatan
        if (empty($data['nama_sub_kegiatan'])) {
            $errors['nama_sub_kegiatan'] = 'Nama sub kegiatan wajib diisi';
        } elseif (strlen(trim($data['nama_sub_kegiatan'])) > 255) {
            $errors['nama_sub_kegiatan'] = 'Nama sub kegiatan maksimal 255 karakter';
        }
        
        return $errors;
    }
    
    /**
     * Show form with validation errors
     */
    private function showFormWithErrors(array $errors, array $data, array $kegiatans, array $seksis): void {
        $pageTitle = isset($data['id']) ? 'Edit Sub Kegiatan' : 'Tambah Sub Kegiatan';
        $activePage = 'sub_kegiatan';
        $viewFile = __DIR__ . '/../../views/sub_kegiatan/form.php';
        $subKegiatan = $data;
        $action = isset($data['id']) ? 'update' : 'store';
        $validationErrors = $errors;
        $seksis = $seksis;
        
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
        $this->redirectWithMessage(base_url('sub-kegiatan'), 'error', 'Terjadi kesalahan sistem');
    }
}

