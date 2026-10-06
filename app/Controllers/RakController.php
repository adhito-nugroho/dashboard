<?php

namespace App\Controllers;

use App\Models\Rak;
use App\Models\Pagu;
use App\Models\Program;
use App\Models\Kegiatan;
use App\Models\SubKegiatan;
use App\Models\Rekening;
use PDOException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;

class RakController {
    private Rak $rakModel;
    private Pagu $paguModel;
    private Program $programModel;
    private Kegiatan $kegiatanModel;
    private SubKegiatan $subKegiatanModel;
    private Rekening $rekeningModel;
    
    // Month names in Indonesian
    private array $bulanNames = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
    ];
    
    public function __construct(
        Rak $rakModel,
        Pagu $paguModel,
        Program $programModel,
        Kegiatan $kegiatanModel,
        SubKegiatan $subKegiatanModel,
        Rekening $rekeningModel
    ) {
        $this->rakModel = $rakModel;
        $this->paguModel = $paguModel;
        $this->programModel = $programModel;
        $this->kegiatanModel = $kegiatanModel;
        $this->subKegiatanModel = $subKegiatanModel;
        $this->rekeningModel = $rekeningModel;
    }
    
    /**
     * Display list of monthly cash plans (grouped by rekening + tahun, then paginated by group)
     */
    public function index(): void {
        try {
            // Read all filter params
            $filterTahun       = isset($_GET['tahun'])          && $_GET['tahun']          !== '' ? (int) $_GET['tahun']          : null;
            $filterKegiatan    = isset($_GET['kegiatan_id'])    && $_GET['kegiatan_id']    !== '' ? (int) $_GET['kegiatan_id']    : null;
            $filterSubKegiatan = isset($_GET['sub_kegiatan_id']) && $_GET['sub_kegiatan_id'] !== '' ? (int) $_GET['sub_kegiatan_id'] : null;

            // Use getWithFilters for all cases (handles null gracefully = no filter)
            $raksFlat = $this->rakModel->getWithFilters($filterTahun, $filterKegiatan, $filterSubKegiatan);

            // Load kegiatan & sub_kegiatan for filter dropdowns
            $filterKegiatanList    = $this->kegiatanModel->getAll();
            $filterSubKegiatanList = $this->subKegiatanModel->getAll();

            // Group by rekening_id + tahun so satu baris = satu rekening+tahun dengan 12 bulan
            $groupedRak = [];
            foreach ($raksFlat as $rak) {
                $key = $rak['rekening_id'] . '_' . $rak['tahun'];
                if (!isset($groupedRak[$key])) {
                    $groupedRak[$key] = [
                        'rekening_id'      => $rak['rekening_id'],
                        'tahun'            => $rak['tahun'],
                        'kode_rekening'    => $rak['kode_rekening'],
                        'nama_rekening'    => $rak['nama_rekening'],
                        'kode_program'     => $rak['kode_program'],
                        'kode_kegiatan'    => $rak['kode_kegiatan'],
                        'kode_sub_kegiatan' => $rak['kode_sub_kegiatan'],
                        'months'           => array_fill(1, 12, 0),
                        'total'            => 0
                    ];
                }
                $groupedRak[$key]['months'][$rak['bulan']] = $rak['nilai_rak'];
                $groupedRak[$key]['total'] += $rak['nilai_rak'];
            }
            $groupedRak = array_values($groupedRak);
            
            $globalTotal = 0;
            foreach ($groupedRak as $gRak) {
                $globalTotal += $gRak['total'];
            }

            $perPage = 10;
            $page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
            $total = count($groupedRak);
            $totalPages = max(1, (int) ceil($total / $perPage));
            if ($page > $totalPages) {
                $page = $totalPages;
            }
            $offset = ($page - 1) * $perPage;
            $raks = array_slice($groupedRak, $offset, $perPage);

            // Build baseUrl with filter params for pagination
            $queryParams = array_filter([
                'tahun'           => $filterTahun,
                'kegiatan_id'     => $filterKegiatan,
                'sub_kegiatan_id' => $filterSubKegiatan,
            ], fn($v) => $v !== null);
            $paginationBase = base_url('rak');
            if (!empty($queryParams)) {
                $paginationBase .= '?' . http_build_query($queryParams) . '&';
            }

            $pagination = [
                'page'       => $page,
                'perPage'    => $perPage,
                'total'      => $total,
                'totalPages' => $totalPages,
                'baseUrl'    => rtrim($paginationBase, '&'),
            ];

            $pageTitle  = 'RAK';
            $activePage = 'rak';
            $viewFile   = __DIR__ . '/../../views/rak/index.php';
            
            include __DIR__ . '/../../views/layout.php';
        } catch (\Exception $e) {
            $this->handleError('Failed to load monthly cash plans: ' . $e->getMessage());
        }
    }
    
    /**
     * Display a summary (Rekap) of RAK grouped by Kegiatan -> Sub Kegiatan -> Rekening
     */
    public function rekap(): void {
        try {
            // Read all filter params
            $filterTahun       = isset($_GET['tahun'])          && $_GET['tahun']          !== '' ? (int) $_GET['tahun']          : date('Y');
            
            // Allow filter
            $raksFlat = $this->rakModel->getWithFilters($filterTahun);

            // Group by kegiatan -> sub_kegiatan -> rekening
            $rekap = [];
            $grandTotal = 0;
            $monthTotals = array_fill(1, 12, 0);

            foreach ($raksFlat as $rak) {
                $kId = $rak['kegiatan_id'];
                $skId = $rak['sub_kegiatan_id'];
                $rId = $rak['rekening_id'];

                if (!isset($rekap[$kId])) {
                    $rekap[$kId] = [
                        'kode_kegiatan' => $rak['kode_kegiatan'],
                        'nama_kegiatan' => $rak['nama_kegiatan'],
                        'total' => 0,
                        'months' => array_fill(1, 12, 0),
                        'sub_kegiatan' => []
                    ];
                }
                
                if (!isset($rekap[$kId]['sub_kegiatan'][$skId])) {
                    $rekap[$kId]['sub_kegiatan'][$skId] = [
                        'kode_sub_kegiatan' => $rak['kode_sub_kegiatan'],
                        'nama_sub_kegiatan' => $rak['nama_sub_kegiatan'],
                        'total' => 0,
                        'months' => array_fill(1, 12, 0),
                        'rekening' => []
                    ];
                }
                
                if (!isset($rekap[$kId]['sub_kegiatan'][$skId]['rekening'][$rId])) {
                    $rekap[$kId]['sub_kegiatan'][$skId]['rekening'][$rId] = [
                        'kode_rekening' => $rak['kode_rekening'],
                        'nama_rekening' => $rak['nama_rekening'],
                        'total' => 0,
                        'months' => array_fill(1, 12, 0)
                    ];
                }
                
                $nilai = (float) $rak['nilai_rak'];
                $bulan = (int) $rak['bulan'];

                $rekap[$kId]['sub_kegiatan'][$skId]['rekening'][$rId]['months'][$bulan] += $nilai;
                $rekap[$kId]['sub_kegiatan'][$skId]['rekening'][$rId]['total'] += $nilai;

                $rekap[$kId]['sub_kegiatan'][$skId]['months'][$bulan] += $nilai;
                $rekap[$kId]['sub_kegiatan'][$skId]['total'] += $nilai;

                $rekap[$kId]['months'][$bulan] += $nilai;
                $rekap[$kId]['total'] += $nilai;
                
                $monthTotals[$bulan] += $nilai;
                $grandTotal += $nilai;
            }

            $pageTitle  = 'Rekap RAK';
            $activePage = 'rekap_rak';
            $viewFile   = __DIR__ . '/../../views/rak/rekap.php';

            include __DIR__ . '/../../views/layout.php';
        } catch (\Exception $e) {
            $this->handleError('Failed to load Rekap RAK: ' . $e->getMessage());
        }
    }
    
    /**
     * Show form for creating/editing monthly cash plan
     */
    public function create(): void {
        try {
            $programs = $this->programModel->getAll();
            
            $pageTitle = 'Tambah RAK';
            $activePage = 'rak';
            $viewFile = __DIR__ . '/../../views/rak/form.php';
            $rak = null;
            $action = 'store';
            $bulanNames = $this->bulanNames;
            
            include __DIR__ . '/../../views/layout.php';
        } catch (\Exception $e) {
            $this->handleError('Failed to load form: ' . $e->getMessage());
        }
    }
    
    /**
     * Show form for editing existing RAK
     */
    public function edit(int $rekeningId, int $tahun): void {
        try {
            $rekening = $this->rekeningModel->getById($rekeningId);
            
            if (!$rekening) {
                $this->redirectWithMessage(base_url('rak'), 'error', 'Rekening tidak ditemukan');
                return;
            }
            
            // Get existing RAK data
            $existingRak = $this->rakModel->getByRekeningAndYear($rekeningId, $tahun);
            $rakData = [];
            foreach ($existingRak as $rak) {
                $rakData[$rak['bulan']] = $rak['nilai_rak'];
            }
            
            // Get pagu for validation
            $pagu = $this->paguModel->getAll();
            $paguForRekening = null;
            foreach ($pagu as $p) {
                if ($p['rekening_id'] == $rekeningId && $p['tahun'] == $tahun) {
                    $paguForRekening = $p;
                    break;
                }
            }
            
            $programs = $this->programModel->getAll();
            $kegiatans = $this->kegiatanModel->getByProgramId($rekening['program_id'] ?? 0);
            $subKegiatans = $this->subKegiatanModel->getByKegiatanId($rekening['kegiatan_id'] ?? 0);
            $rekenings = $this->rekeningModel->getBySubKegiatanId($rekening['sub_kegiatan_id'] ?? 0);
            
            $pageTitle = 'Edit RAK';
            $activePage = 'rak';
            $viewFile = __DIR__ . '/../../views/rak/form.php';
            $action = 'update';
            $rak = [
                'rekening_id' => $rekeningId,
                'tahun' => $tahun,
                'program_id' => $rekening['program_id'] ?? '',
                'kegiatan_id' => $rekening['kegiatan_id'] ?? '',
                'sub_kegiatan_id' => $rekening['sub_kegiatan_id'] ?? '',
                'rak_data' => $rakData,
                'pagu' => $paguForRekening
            ];
            $bulanNames = $this->bulanNames;
            
            include __DIR__ . '/../../views/layout.php';
        } catch (\Exception $e) {
            $this->handleError('Gagal memuat RAK: ' . $e->getMessage());
        }
    }
    
    /**
     * Store new monthly cash plan
     */
    public function store(): void {
        $errors = $this->validate($_POST);
        
        if (!empty($errors)) {
            try {
                $programs = $this->programModel->getAll();
                $this->showFormWithErrors($errors, $_POST, $programs, 'store');
            } catch (\Exception $e) {
                $this->handleError('Failed to load form: ' . $e->getMessage());
            }
            return;
        }
        
        try {
            $rekeningId = (int) $_POST['rekening_id'];
            $tahun = (int) $_POST['tahun'];
            $bulanData = [];
            
            // Collect monthly data (format id: 1.500.000 → 1500000)
            for ($bulan = 1; $bulan <= 12; $bulan++) {
                $key = 'bulan_' . $bulan;
                if (isset($_POST[$key]) && $_POST[$key] !== '') {
                    $raw = $this->normalizeNilaiRak($_POST[$key]);
                    $bulanData[$bulan] = $raw !== null && is_numeric($raw) ? (float) $raw : 0;
                } else {
                    $bulanData[$bulan] = 0;
                }
            }
            
            $this->rakModel->saveMonthlyData($rekeningId, $tahun, $bulanData);
            
            $this->redirectWithMessage(base_url('rak'), 'success', 'RAK berhasil disimpan');
        } catch (\Exception $e) {
            $this->handleError('Gagal menyimpan RAK: ' . $e->getMessage());
        }
    }
    
    /**
     * Update monthly cash plan
     */
    public function update(int $rekeningId, int $tahun): void {
        $errors = $this->validate($_POST, $rekeningId, $tahun);
        
        if (!empty($errors)) {
            try {
                $programs = $this->programModel->getAll();
                $rak = array_merge(['rekening_id' => $rekeningId, 'tahun' => $tahun], $_POST);
                $this->showFormWithErrors($errors, $rak, $programs, 'update');
            } catch (\Exception $e) {
                $this->handleError('Failed to load form: ' . $e->getMessage());
            }
            return;
        }
        
        try {
            $bulanData = [];
            
            // Collect monthly data (format id: 1.500.000 → 1500000)
            for ($bulan = 1; $bulan <= 12; $bulan++) {
                $key = 'bulan_' . $bulan;
                if (isset($_POST[$key]) && $_POST[$key] !== '') {
                    $raw = $this->normalizeNilaiRak($_POST[$key]);
                    $bulanData[$bulan] = $raw !== null && is_numeric($raw) ? (float) $raw : 0;
                } else {
                    $bulanData[$bulan] = 0;
                }
            }
            
            $this->rakModel->saveMonthlyData($rekeningId, $tahun, $bulanData);
            
            $this->redirectWithMessage(base_url('rak'), 'success', 'RAK berhasil diperbarui');
        } catch (\Exception $e) {
            $this->handleError('Gagal memperbarui RAK: ' . $e->getMessage());
        }
    }
    
    /**
     * Delete monthly cash plan
     */
    public function delete(int $rekeningId, int $tahun): void {
        try {
            $this->rakModel->deleteByRekeningAndYear($rekeningId, $tahun);
            $this->redirectWithMessage(base_url('rak'), 'success', 'RAK berhasil dihapus');
        } catch (\Exception $e) {
            $this->redirectWithMessage(base_url('rak'), 'error', 'Gagal menghapus RAK: ' . $e->getMessage());
        }
    }
    
    /**
     * AJAX: Get rekening and check pagu
     */
    public function getRekeningInfo(): void {
        header('Content-Type: application/json');
        try {
            $rekeningId = (int) ($_GET['rekening_id'] ?? 0);
            $tahun = (int) ($_GET['tahun'] ?? 0);
            
            if (!$rekeningId || !$tahun) {
                echo json_encode(['error' => 'Rekening ID and Tahun are required']);
                exit;
            }
            
            // Get pagu for this rekening and year
            $pagus = $this->paguModel->getAll();
            $pagu = null;
            foreach ($pagus as $p) {
                if ($p['rekening_id'] == $rekeningId && $p['tahun'] == $tahun) {
                    $pagu = $p;
                    break;
                }
            }
            
            // Get existing RAK total
            $totalRak = $this->rakModel->getTotalByRekeningAndYear($rekeningId, $tahun);
            
            echo json_encode([
                'pagu' => $pagu ? [
                    'id' => $pagu['id'],
                    'nilai_pagu' => (float) $pagu['nilai_pagu']
                ] : null,
                'total_rak' => $totalRak,
                'sisa_pagu' => $pagu ? ((float) $pagu['nilai_pagu'] - $totalRak) : 0
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit;
    }
    
    /**
     * Normalize nilai RAK dari format tampilan (1.500.000 atau 1,500,000) ke angka murni.
     *
     * @param string $value
     * @return string|null String angka tanpa pemisah ribuan, atau null jika invalid
     */
    private function normalizeNilaiRak(string $value): ?string {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        // Hapus pemisah ribuan: titik (id) dan koma (en)
        $normalized = str_replace([',', '.'], '', $value);
        return $normalized !== '' && is_numeric($normalized) ? $normalized : null;
    }

    /**
     * Validate form data
     *
     * @param array $data
     * @param int|null $rekeningId For update validation
     * @param int|null $tahun For update validation
     * @return array Errors
     */
    private function validate(array $data, ?int $rekeningId = null, ?int $tahun = null): array {
        $errors = [];
        
        $currentRekeningId = $rekeningId ?? (int) ($data['rekening_id'] ?? 0);
        $currentTahun = $tahun ?? (int) ($data['tahun'] ?? 0);
        
        // Validate rekening_id
        if (empty($currentRekeningId)) {
            $errors['rekening_id'] = 'Rekening wajib dipilih';
        }
        
        // Validate tahun
        if (empty($currentTahun)) {
            $errors['tahun'] = 'Tahun wajib diisi';
        } elseif ($currentTahun < 2000 || $currentTahun > 2100) {
            $errors['tahun'] = 'Tahun harus antara 2000 dan 2100';
        }
        
        // Validate monthly values and calculate total (format id: 1.500.000 → 1500000)
        $totalRak = 0;
        for ($bulan = 1; $bulan <= 12; $bulan++) {
            $key = 'bulan_' . $bulan;
            if (isset($data[$key]) && $data[$key] !== '') {
                $nilai = $this->normalizeNilaiRak($data[$key]);
                if ($nilai === null) {
                    $errors[$key] = 'Nilai harus berupa angka';
                } elseif ((float) $nilai < 0) {
                    $errors[$key] = 'Nilai tidak boleh negatif';
                } else {
                    $totalRak += (float) $nilai;
                }
            }
        }
        
        // Check if total RAK exceeds pagu
        if (empty($errors['rekening_id']) && empty($errors['tahun'])) {
            $pagus = $this->paguModel->getAll();
            $pagu = null;
            foreach ($pagus as $p) {
                if ($p['rekening_id'] == $currentRekeningId && $p['tahun'] == $currentTahun) {
                    $pagu = $p;
                    break;
                }
            }
            
            if (!$pagu) {
                $errors['rekening_id'] = 'Pagu untuk rekening dan tahun ini belum ditetapkan';
            } else {
                // Get existing RAK total (for update, we need to subtract it since we're replacing)
                $existingTotal = 0;
                if ($rekeningId && $tahun) {
                    // This is an update, get existing total
                    $existingTotal = $this->rakModel->getTotalByRekeningAndYear($currentRekeningId, $currentTahun);
                }
                $availablePagu = (float) $pagu['nilai_pagu'] - $existingTotal;
                
                if ($totalRak > $availablePagu) {
                    $errors['total'] = sprintf(
                        'Total RAK (Rp %s) melebihi sisa pagu yang tersedia (Rp %s)',
                        number_format($totalRak, 0, ',', '.'),
                        number_format($availablePagu, 0, ',', '.')
                    );
                }
            }
        }
        
        return $errors;
    }
    
    /**
     * Show form with validation errors (populate cascading dropdowns from POST)
     * @param string $action 'store' or 'update'
     */
    private function showFormWithErrors(array $errors, array $data, array $programs, string $action = 'store'): void {
        $programId = (int) ($data['program_id'] ?? 0);
        $kegiatanId = (int) ($data['kegiatan_id'] ?? 0);
        $subKegiatanId = (int) ($data['sub_kegiatan_id'] ?? 0);
        $kegiatans = $programId ? $this->kegiatanModel->getByProgramId($programId) : [];
        $subKegiatans = $kegiatanId ? $this->subKegiatanModel->getByKegiatanId($kegiatanId) : [];
        $rekenings = $subKegiatanId ? $this->rekeningModel->getBySubKegiatanId($subKegiatanId) : [];

        $pageTitle = $action === 'update' ? 'Edit RAK' : 'Tambah RAK';
        $activePage = 'rak';
        $viewFile = __DIR__ . '/../../views/rak/form.php';
        $rak = $data;
        $validationErrors = $errors;
        $bulanNames = $this->bulanNames;

        include __DIR__ . '/../../views/layout.php';
    }
    
    /**
     * Membangun objek Spreadsheet RAK berdasarkan filter.
     */
    public function buildSpreadsheet(?int $filterTahun = null, ?int $filterKegiatan = null, ?int $filterSubKegiatan = null): Spreadsheet {
        $raksFlat = $this->rakModel->getWithFilters($filterTahun, $filterKegiatan, $filterSubKegiatan);

        // Group by rekening_id + tahun
        $groupedRak = [];
        foreach ($raksFlat as $rak) {
            $key = $rak['rekening_id'] . '_' . $rak['tahun'];
            if (!isset($groupedRak[$key])) {
                $groupedRak[$key] = [
                    'rekening_id'       => $rak['rekening_id'],
                    'tahun'             => $rak['tahun'],
                    'kode_rekening'     => $rak['kode_rekening'],
                    'nama_rekening'     => $rak['nama_rekening'],
                    'kode_program'      => $rak['kode_program'] ?? '',
                    'nama_program'      => $rak['nama_program'] ?? '',
                    'kode_kegiatan'     => $rak['kode_kegiatan'] ?? '',
                    'nama_kegiatan'     => $rak['nama_kegiatan'] ?? '',
                    'kode_sub_kegiatan' => $rak['kode_sub_kegiatan'] ?? '',
                    'nama_sub_kegiatan' => $rak['nama_sub_kegiatan'] ?? '',
                    'months'            => array_fill(1, 12, 0),
                    'total'             => 0
                ];
            }
            $groupedRak[$key]['months'][$rak['bulan']] = (float) $rak['nilai_rak'];
            $groupedRak[$key]['total'] += (float) $rak['nilai_rak'];
        }
        $groupedRak = array_values($groupedRak);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('RAK');

        // 1. Header Metadata
        $sheet->setCellValue('A1', 'RENCANA ANGGARAN KAS (RAK)');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $filterInfo = [];
        $filterInfo[] = 'Tahun: ' . ($filterTahun !== null ? $filterTahun : 'Semua');
        if ($filterKegiatan !== null) {
            $keg = $this->kegiatanModel->getById($filterKegiatan);
            if ($keg) $filterInfo[] = 'Kegiatan: ' . ($keg['kode_kegiatan'] . ' - ' . $keg['nama_kegiatan']);
        }
        if ($filterSubKegiatan !== null) {
            $subKeg = $this->subKegiatanModel->getById($filterSubKegiatan);
            if ($subKeg) $filterInfo[] = 'Sub Kegiatan: ' . ($subKeg['kode_sub_kegiatan'] . ' - ' . $subKeg['nama_sub_kegiatan']);
        }
        $sheet->setCellValue('A2', implode(' | ', $filterInfo));
        $sheet->getStyle('A2')->getFont()->setItalic(true)->setSize(10);
        $sheet->setCellValue('A3', 'Diunduh pada: ' . date('d/m/Y H:i:s'));
        $sheet->getStyle('A3')->getFont()->setSize(9)->getColor()->setRGB('666666');

        // 2. Table Headers (Row 5)
        $headers = [
            'A5' => 'No',
            'B5' => 'Kode Rekening',
            'C5' => 'Nama Rekening',
            'D5' => 'Program',
            'E5' => 'Kegiatan',
            'F5' => 'Sub Kegiatan',
            'G5' => 'Tahun',
            'H5' => 'Jan',
            'I5' => 'Feb',
            'J5' => 'Mar',
            'K5' => 'Apr',
            'L5' => 'Mei',
            'M5' => 'Jun',
            'N5' => 'Jul',
            'O5' => 'Agu',
            'P5' => 'Sep',
            'Q5' => 'Okt',
            'R5' => 'Nov',
            'S5' => 'Des',
            'T5' => 'Total (Rp)'
        ];

        foreach ($headers as $cell => $val) {
            $sheet->setCellValue($cell, $val);
        }

        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A5F']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'FFFFFF']]]
        ];
        $sheet->getStyle('A5:T5')->applyFromArray($headerStyle);
        $sheet->getRowDimension(5)->setRowHeight(26);

        // 3. Data Rows
        $row = 6;
        $numFmt = '#,##0';
        $no = 1;

        if (empty($groupedRak)) {
            $sheet->setCellValue("A{$row}", 'Tidak ada data RAK sesuai filter yang dipilih');
            $sheet->mergeCells("A{$row}:T{$row}");
            $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("A{$row}:T{$row}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('CCCCCC');
            $row++;
        } else {
            foreach ($groupedRak as $item) {
                $sheet->setCellValue("A{$row}", $no++);
                $sheet->setCellValueExplicit("B{$row}", $item['kode_rekening'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValue("C{$row}", $item['nama_rekening']);
                $sheet->setCellValue("D{$row}", $item['kode_program'] . ' - ' . $item['nama_program']);
                $sheet->setCellValue("E{$row}", $item['kode_kegiatan'] . ' - ' . $item['nama_kegiatan']);
                $sheet->setCellValue("F{$row}", $item['kode_sub_kegiatan'] . ' - ' . $item['nama_sub_kegiatan']);
                $sheet->setCellValue("G{$row}", $item['tahun']);

                $colIndex = 'H';
                for ($m = 1; $m <= 12; $m++) {
                    $sheet->setCellValue("{$colIndex}{$row}", $item['months'][$m]);
                    $sheet->getStyle("{$colIndex}{$row}")->getNumberFormat()->setFormatCode($numFmt);
                    $colIndex++;
                }

                $sheet->setCellValue("T{$row}", "=SUM(H{$row}:S{$row})");
                $sheet->getStyle("T{$row}")->getNumberFormat()->setFormatCode($numFmt);

                // Alignments & border
                $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("B{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("G{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("H{$row}:T{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet->getStyle("A{$row}:T{$row}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('D1D5DB');

                $row++;
            }

            // 4. Grand Total Row
            $sheet->setCellValue("A{$row}", 'TOTAL KESELURUHAN');
            $sheet->mergeCells("A{$row}:G{$row}");
            $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $lastDataRow = $row - 1;
            $colIndex = 'H';
            for ($m = 1; $m <= 12; $m++) {
                $sheet->setCellValue("{$colIndex}{$row}", "=SUM({$colIndex}6:{$colIndex}{$lastDataRow})");
                $sheet->getStyle("{$colIndex}{$row}")->getNumberFormat()->setFormatCode($numFmt);
                $sheet->getStyle("{$colIndex}{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $colIndex++;
            }
            $sheet->setCellValue("T{$row}", "=SUM(T6:T{$lastDataRow})");
            $sheet->getStyle("T{$row}")->getNumberFormat()->setFormatCode($numFmt);
            $sheet->getStyle("T{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

            $totalStyle = [
                'font' => ['bold' => true, 'color' => ['rgb' => '1E293B']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FEF08A']],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '94A3B8']]]
            ];
            $sheet->getStyle("A{$row}:T{$row}")->applyFromArray($totalStyle);
            $sheet->getRowDimension($row)->setRowHeight(22);
        }

        // Auto width for columns A to T
        foreach (range('A', 'T') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return $spreadsheet;
    }

    /**
     * Download file Excel RAK sesuai filter.
     */
    public function export(): void {
        try {
            $filterTahun       = isset($_GET['tahun'])          && $_GET['tahun']          !== '' ? (int) $_GET['tahun']          : null;
            $filterKegiatan    = isset($_GET['kegiatan_id'])    && $_GET['kegiatan_id']    !== '' ? (int) $_GET['kegiatan_id']    : null;
            $filterSubKegiatan = isset($_GET['sub_kegiatan_id']) && $_GET['sub_kegiatan_id'] !== '' ? (int) $_GET['sub_kegiatan_id'] : null;

            $spreadsheet = $this->buildSpreadsheet($filterTahun, $filterKegiatan, $filterSubKegiatan);

            $tahunLabel = $filterTahun !== null ? (string)$filterTahun : 'Semua';
            $filename = 'RAK_' . $tahunLabel . '_' . date('Ymd_His') . '.xlsx';

            if (ob_get_length()) {
                ob_end_clean();
            }

            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="' . $filename . '"');
            header('Cache-Control: max-age=0');

            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            exit;
        } catch (\Exception $e) {
            error_log('Error exporting RAK: ' . $e->getMessage());
            $this->redirectWithMessage(base_url('rak'), 'error', 'Gagal mengekspor data RAK: ' . $e->getMessage());
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
        $this->redirectWithMessage(base_url('rak'), 'error', 'Terjadi kesalahan sistem');
    }
}

