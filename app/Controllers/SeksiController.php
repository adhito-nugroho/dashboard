<?php

namespace App\Controllers;

use App\Models\Seksi;
use PDOException;

class SeksiController {
    private Seksi $seksiModel;
    
    public function __construct(Seksi $seksiModel) {
        $this->seksiModel = $seksiModel;
    }
    
    /**
     * Display list of sections
     */
    public function index(): void {
        try {
            $seksis = $this->seksiModel->getAll();
            
            $perPage = 10;
            $page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
            $total = count($seksis);
            $totalPages = max(1, (int) ceil($total / $perPage));
            if ($page > $totalPages) {
                $page = $totalPages;
            }
            $offset = ($page - 1) * $perPage;
            $seksis = array_slice($seksis, $offset, $perPage);
            $pagination = [
                'page' => $page,
                'perPage' => $perPage,
                'total' => $total,
                'totalPages' => $totalPages,
                'baseUrl' => base_url('seksi')
            ];

            $pageTitle = 'Seksi';
            $activePage = 'seksi';
            $viewFile = __DIR__ . '/../../views/seksi/index.php';

            include __DIR__ . '/../../views/layout.php';
        } catch (\Exception $e) {
            // Jangan redirect ke /seksi lagi di sini karena bisa menyebabkan loop redirect
            error_log('Failed to load sections: ' . $e->getMessage());
            http_response_code(500);
            echo '<h1>Terjadi kesalahan saat memuat data seksi</h1>';
            echo '<p>Silakan cek konfigurasi database atau tabel <code>seksi</code>.</p>';
            echo '<p>Detail error sudah dicatat di log server.</p>';
        }
    }
    
    /**
     * Show form for creating new section
     */
    public function create(): void {
        $pageTitle = 'Tambah Seksi';
        $activePage = 'seksi';
        $viewFile = __DIR__ . '/../../views/seksi/form.php';
        $seksi = null; // New record
        $action = 'store';
        
        include __DIR__ . '/../../views/layout.php';
    }
    
    /**
     * Store new section
     */
    public function store(): void {
        $errors = $this->validate($_POST);
        
        if (!empty($errors)) {
            $this->showFormWithErrors($errors, $_POST);
            return;
        }
        
        try {
            $this->seksiModel->create(
                trim($_POST['kode_seksi']),
                trim($_POST['nama_seksi'])
            );
            
            $this->redirectWithMessage(base_url('seksi'), 'success', 'Seksi berhasil ditambahkan');
        } catch (\Exception $e) {
            $this->handleError('Gagal menambahkan seksi: ' . $e->getMessage());
        }
    }
    
    /**
     * Show form for editing section
     */
    public function edit(int $id): void {
        try {
            $seksi = $this->seksiModel->getById($id);
            
            if (!$seksi) {
                $this->redirectWithMessage(base_url('seksi'), 'error', 'Seksi tidak ditemukan');
                return;
            }
            
            $pageTitle = 'Edit Seksi';
            $activePage = 'seksi';
            $viewFile = __DIR__ . '/../../views/seksi/form.php';
            $action = 'update';
            
            include __DIR__ . '/../../views/layout.php';
        } catch (\Exception $e) {
            $this->handleError('Gagal memuat seksi: ' . $e->getMessage());
        }
    }
    
    /**
     * Update section
     */
    public function update(int $id): void {
        $errors = $this->validate($_POST, $id);
        
        if (!empty($errors)) {
            $seksi = array_merge(['id' => $id], $_POST);
            $this->showFormWithErrors($errors, $seksi);
            return;
        }
        
        try {
            $this->seksiModel->update(
                $id,
                trim($_POST['kode_seksi']),
                trim($_POST['nama_seksi'])
            );
            
            $this->redirectWithMessage(base_url('seksi'), 'success', 'Seksi berhasil diperbarui');
        } catch (\Exception $e) {
            $this->handleError('Gagal memperbarui seksi: ' . $e->getMessage());
        }
    }
    
    /**
     * Delete section
     */
    public function delete(int $id): void {
        try {
            $this->seksiModel->delete($id);
            $this->redirectWithMessage(base_url('seksi'), 'success', 'Seksi berhasil dihapus');
        } catch (\Exception $e) {
            $this->redirectWithMessage(base_url('seksi'), 'error', 'Gagal menghapus seksi: ' . $e->getMessage());
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
        
        // Validate kode_seksi
        if (empty($data['kode_seksi'])) {
            $errors['kode_seksi'] = 'Kode seksi wajib diisi';
        } elseif (strlen(trim($data['kode_seksi'])) > 50) {
            $errors['kode_seksi'] = 'Kode seksi maksimal 50 karakter';
        } elseif ($this->seksiModel->kodeExists(trim($data['kode_seksi']), $excludeId)) {
            $errors['kode_seksi'] = 'Kode seksi sudah digunakan';
        }
        
        // Validate nama_seksi
        if (empty($data['nama_seksi'])) {
            $errors['nama_seksi'] = 'Nama seksi wajib diisi';
        } elseif (strlen(trim($data['nama_seksi'])) > 255) {
            $errors['nama_seksi'] = 'Nama seksi maksimal 255 karakter';
        }
        
        return $errors;
    }
    
    /**
     * Show form with validation errors
     */
    private function showFormWithErrors(array $errors, array $data): void {
        $pageTitle = isset($data['id']) ? 'Edit Seksi' : 'Tambah Seksi';
        $activePage = 'seksi';
        $viewFile = __DIR__ . '/../../views/seksi/form.php';
        $seksi = $data;
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
        $this->redirectWithMessage(base_url('seksi'), 'error', 'Terjadi kesalahan sistem');
    }
}

