<?php

namespace App\Controllers;

use App\Models\Pagu;
use App\Models\PaguRiwayat;
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
    private ?PaguRiwayat $riwayatModel;

    public function __construct(
        Pagu $paguModel,
        Program $programModel,
        Kegiatan $kegiatanModel,
        SubKegiatan $subKegiatanModel,
        Rekening $rekeningModel,
        ?PaguRiwayat $riwayatModel = null
    ) {
        $this->paguModel = $paguModel;
        $this->programModel = $programModel;
        $this->kegiatanModel = $kegiatanModel;
        $this->subKegiatanModel = $subKegiatanModel;
        $this->rekeningModel = $rekeningModel;
        $this->riwayatModel = $riwayatModel;
    }

    private function getRiwayatModel(): ?PaguRiwayat
    {
        if ($this->riwayatModel !== null) {
            return $this->riwayatModel;
        }
        try {
            $this->riwayatModel = new PaguRiwayat(\Database::getConnection());
            // Pastikan tabel ada (aman bila migrasi belum dijalankan)
            $this->riwayatModel->getRingkasanByTahun((int) date('Y'));
            return $this->riwayatModel;
        } catch (\Throwable) {
            return null;
        }
    }

    private function tableHasPaguRiwayat(): bool
    {
        try {
            $db = \Database::getConnection();
            $stmt = $db->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
            $stmt->execute(['pagu_riwayat']);
            return (int) $stmt->fetchColumn() > 0;
        } catch (\Throwable) {
            return false;
        }
    }
    
    /**
     * Display list of budget allocations
     */
    public function index(): void {
        try {
            $pagus = $this->paguModel->getAll();
            $tahunFilter = isset($_GET['tahun']) && is_numeric($_GET['tahun']) ? (int) $_GET['tahun'] : null;
            if ($tahunFilter) {
                $pagus = array_values(array_filter($pagus, fn($p) => (int) ($p['tahun'] ?? 0) === $tahunFilter));
            }
            $tahunLaporan = $tahunFilter ?? (int) date('Y');

            // Total keseluruhan (dari SEMUA data tampil, bukan hanya halaman aktif)
            $totalPaguKeseluruhan = array_sum(array_map(
                fn($p) => (float) ($p['nilai_pagu'] ?? 0),
                $pagus
            ));

            // --- PAPBD: lengkapi tiap baris dengan pagu_awal, selisih, realisasi, sisa ---
            $hasRiwayat = $this->tableHasPaguRiwayat();
            $nilaiAwalMap = [];
            $realisasiMap = [];
            $ringkasanPerubahan = ['jumlah_perubahan' => 0, 'total_tambah' => 0.0, 'total_kurang' => 0.0, 'total_selisih' => 0.0, 'jumlah_rekening_berubah' => 0];
            if ($hasRiwayat && $this->getRiwayatModel() !== null) {
                $nilaiAwalMap = $this->getRiwayatModel()->getNilaiAwalMapByTahun($tahunLaporan);
                $ringkasanPerubahan = $this->getRiwayatModel()->getRingkasanByTahun($tahunLaporan);
            }
            // Realisasi per rekening untuk tahun laporan (fallback: semua tahun bila filter kosong)
            try {
                $realisasiMap = $this->paguModel->getRealisasiMapByTahun($tahunLaporan);
            } catch (\Throwable) {
                $realisasiMap = [];
            }
            $totalPaguAwal = 0.0;
            foreach ($pagus as &$p) {
                $pid = (int) ($p['id'] ?? 0);
                $rid = (int) ($p['rekening_id'] ?? 0);
                $nilaiAkhir = (float) ($p['nilai_pagu'] ?? 0);
                // Nilai awal = log pertama bila ada, else nilai saat ini (dianggap APBD awal)
                $nilaiAwal = $nilaiAwalMap[$pid] ?? $nilaiAkhir;
                $p['pagu_awal'] = $nilaiAwal;
                $p['selisih'] = $nilaiAkhir - $nilaiAwal;
                $p['realisasi'] = $realisasiMap[$rid] ?? 0.0;
                $p['sisa_baru'] = $nilaiAkhir - (float) $p['realisasi'];
                $p['status_ubah'] = $p['selisih'] > 0 ? 'tambah' : ($p['selisih'] < 0 ? 'kurang' : 'tetap');
                $p['over_realisasi'] = (float) $p['realisasi'] > $nilaiAkhir;
                $totalPaguAwal += $nilaiAwal;
            }
            unset($p);
            
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

            // --- PAPBD info box: realisasi + pagu awal + riwayat ---
            $pagu['realisasi'] = $this->paguModel->getRealisasi((int) $pagu['rekening_id'], (int) $pagu['tahun']);
            $pagu['sisa'] = (float) $pagu['nilai_pagu'] - (float) $pagu['realisasi'];
            $pagu['pagu_awal'] = (float) $pagu['nilai_pagu'];
            $pagu['riwayat'] = [];
            if ($this->tableHasPaguRiwayat() && $this->getRiwayatModel() !== null) {
                $pagu['riwayat'] = $this->getRiwayatModel()->getByPaguId($id);
                $awal = $this->getRiwayatModel()->getNilaiAwal($id);
                if ($awal !== null) {
                    $pagu['pagu_awal'] = $awal;
                }
            }

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
            $lama = $this->paguModel->getById($id);
            if (!$lama) {
                $this->redirectWithMessage(base_url('pagu'), 'error', 'Pagu tidak ditemukan');
                return;
            }
            $nilaiSebelum = (float) $lama['nilai_pagu'];
            $nilaiSesudah = (float) str_replace(',', '.', str_replace('.', '', $_POST['nilai_pagu']));
            $rekeningBaru = (int) $_POST['rekening_id'];
            $tahunBaru = (int) $_POST['tahun'];

            $this->paguModel->update($id, $rekeningBaru, $tahunBaru, $nilaiSesudah);

            // --- PAPBD: catat riwayat bila nilai/relasi berubah ---
            try {
                if ($this->tableHasPaguRiwayat() && $this->getRiwayatModel() !== null) {
                    $berubah = abs($nilaiSesudah - $nilaiSebelum) > 0.009
                        || $rekeningBaru !== (int) $lama['rekening_id']
                        || $tahunBaru !== (int) $lama['tahun'];
                    if ($berubah) {
                        $this->getRiwayatModel()->log(
                            $id,
                            $rekeningBaru,
                            $tahunBaru,
                            $nilaiSebelum,
                            $nilaiSesudah,
                            $_POST['jenis_perubahan'] ?? 'PAPBD',
                            $_POST['keterangan_perubahan'] ?? null,
                            isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null
                        );
                    }
                }
            } catch (\Throwable $e) {
                error_log('Gagal mencatat riwayat PAPBD: ' . $e->getMessage());
            }

            // --- Peringatan RAK bila pagu turun di bawah total RAK ---
            $peringatan = '';
            try {
                $db = \Database::getConnection();
                $stmt = $db->prepare('SELECT COALESCE(SUM(nilai_rak),0) FROM rak WHERE rekening_id = :rid AND tahun = :thn');
                $stmt->execute([':rid' => $rekeningBaru, ':thn' => $tahunBaru]);
                $totalRak = (float) $stmt->fetchColumn();
                if ($totalRak > $nilaiSesudah) {
                    $peringatan = sprintf(
                        ' Peringatan: total RAK (Rp %s) kini melebihi pagu baru (Rp %s). Sesuaikan RAK.',
                        number_format($totalRak, 0, ',', '.'),
                        number_format($nilaiSesudah, 0, ',', '.')
                    );
                }
            } catch (\Throwable) {
            }

            $this->redirectWithMessage(base_url('pagu'), 'success', 'Pagu berhasil diperbarui (PAPBD tercatat).' . $peringatan);
        } catch (\Exception $e) {
            $this->handleError('Gagal memperbarui pagu: ' . $e->getMessage());
        }
    }
    
    /**
     * Delete budget allocation
     */
    public function delete(int $id): void {
        try {
            $lama = $this->paguModel->getById($id);
            if ($lama) {
                $realisasi = $this->paguModel->getRealisasi((int) $lama['rekening_id'], (int) $lama['tahun']);
                if ($realisasi > 0) {
                    $this->redirectWithMessage(base_url('pagu'), 'error', sprintf(
                        'Pagu tidak bisa dihapus karena sudah ada realisasi terverifikasi Rp %s.',
                        number_format($realisasi, 0, ',', '.')
                    ));
                    return;
                }
            }
            $this->paguModel->delete($id);
            try {
                if ($this->tableHasPaguRiwayat()) {
                    $db = \Database::getConnection();
                    $stmt = $db->prepare('DELETE FROM pagu_riwayat WHERE pagu_id = :id');
                    $stmt->execute([':id' => $id]);
                }
            } catch (\Throwable) {
            }
            $this->redirectWithMessage(base_url('pagu'), 'success', 'Pagu berhasil dihapus');
        } catch (\Exception $e) {
            $this->redirectWithMessage(base_url('pagu'), 'error', 'Gagal menghapus pagu: ' . $e->getMessage());
        }
    }

    /**
     * Halaman riwayat perubahan satu pagu (APBD -> PAPBD).
     */
    public function riwayat(int $id): void
    {
        try {
            $pagu = $this->paguModel->getById($id);
            if (!$pagu) {
                $this->redirectWithMessage(base_url('pagu'), 'error', 'Pagu tidak ditemukan');
                return;
            }
            $riwayat = $this->tableHasPaguRiwayat() && $this->getRiwayatModel() !== null
                ? $this->getRiwayatModel()->getByPaguId($id)
                : [];
            $nilaiAwal = $pagu['nilai_pagu'];
            if (!empty($riwayat)) {
                $nilaiAwal = end($riwayat)['nilai_sebelum'] ?? $riwayat[count($riwayat) - 1]['nilai_sebelum'];
                // end() menggeser pointer; ambil log pertama = nilai awal
                $first = $riwayat[count($riwayat) - 1];
                $nilaiAwal = (float) ($first['nilai_sebelum'] ?? $pagu['nilai_pagu']);
            }
            $realisasi = $this->paguModel->getRealisasi((int) $pagu['rekening_id'], (int) $pagu['tahun']);

            $pageTitle = 'Riwayat PAPBD';
            $activePage = 'pagu';
            $viewFile = __DIR__ . '/../../views/pagu/riwayat.php';

            include __DIR__ . '/../../views/layout.php';
        } catch (\Exception $e) {
            $this->handleError('Gagal memuat riwayat pagu: ' . $e->getMessage());
        }
    }

    /**
     * Laporan selisih APBD vs PAPBD per tahun.
     */
    public function laporan(): void
    {
        try {
            $tahun = isset($_GET['tahun']) && is_numeric($_GET['tahun']) ? (int) $_GET['tahun'] : (int) date('Y');
            $pagus = array_values(array_filter(
                $this->paguModel->getAll(),
                fn($p) => (int) ($p['tahun'] ?? 0) === $tahun
            ));
            $nilaiAwalMap = $this->tableHasPaguRiwayat() && $this->getRiwayatModel() !== null
                ? $this->getRiwayatModel()->getNilaiAwalMapByTahun($tahun)
                : [];
            try {
                $realisasiMap = $this->paguModel->getRealisasiMapByTahun($tahun);
            } catch (\Throwable) {
                $realisasiMap = [];
            }
            $rows = [];
            $totAwal = 0.0; $totAkhir = 0.0; $totReal = 0.0;
            foreach ($pagus as $p) {
                $awal = (float) ($nilaiAwalMap[(int) $p['id']] ?? $p['nilai_pagu']);
                $akhir = (float) $p['nilai_pagu'];
                $real = (float) ($realisasiMap[(int) $p['rekening_id']] ?? 0);
                $rows[] = array_merge($p, [
                    'pagu_awal' => $awal,
                    'selisih' => $akhir - $awal,
                    'realisasi' => $real,
                    'sisa_baru' => $akhir - $real,
                ]);
                $totAwal += $awal; $totAkhir += $akhir; $totReal += $real;
            }
            usort($rows, fn($a, $b) => strcmp(($a['kode_rekening'] ?? ''), ($b['kode_rekening'] ?? '')));
            $ringkasan = $this->tableHasPaguRiwayat() && $this->getRiwayatModel() !== null
                ? $this->getRiwayatModel()->getRingkasanByTahun($tahun)
                : ['jumlah_perubahan' => 0, 'total_tambah' => 0.0, 'total_kurang' => 0.0, 'total_selisih' => 0.0, 'jumlah_rekening_berubah' => 0];

            $pageTitle = 'Laporan Selisih APBD vs PAPBD';
            $activePage = 'pagu';
            $viewFile = __DIR__ . '/../../views/pagu/laporan.php';

            include __DIR__ . '/../../views/layout.php';
        } catch (\Exception $e) {
            $this->handleError('Gagal memuat laporan PAPBD: ' . $e->getMessage());
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

        // PAPBD: pagu baru tidak boleh di bawah realisasi terverifikasi
        if (empty($errors['nilai_pagu']) && empty($errors['rekening_id']) && empty($errors['tahun'])) {
            $nilaiBaru = (float) str_replace(',', '.', str_replace('.', '', $data['nilai_pagu']));
            $realisasi = $this->paguModel->getRealisasi((int) $data['rekening_id'], (int) $data['tahun']);
            // Koreksi relasi pindah rekening pada mode edit: realisasi ikut rekening baru
            if ($realisasi > 0 && $nilaiBaru + 0.009 < $realisasi) {
                $errors['nilai_pagu'] = sprintf(
                    'Pagu baru (Rp %s) tidak boleh di bawah realisasi terverifikasi (Rp %s). Naikkan pagu atau batalkan verifikasi transaksi dulu.',
                    number_format($nilaiBaru, 0, ',', '.'),
                    number_format($realisasi, 0, ',', '.')
                );
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

