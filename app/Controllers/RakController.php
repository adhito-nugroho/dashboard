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
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

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
     * Susunan hierarki seperti Laporan Realisasi:
     * PROGRAM -> KEGIATAN -> SUB KEGIATAN -> REKENING,
     * dengan kolom bulanan Jan..Des + Total RAK.
     */
    public function buildSpreadsheet(?int $filterTahun = null, ?int $filterKegiatan = null, ?int $filterSubKegiatan = null): Spreadsheet {
        $raksFlat = $this->rakModel->getWithFilters($filterTahun, $filterKegiatan, $filterSubKegiatan);

        // Group: Program -> Kegiatan -> Sub Kegiatan -> Rekening
        $hierarchy = [];
        foreach ($raksFlat as $rak) {
            $pKey = $rak['kode_program'] ?? '';
            $kid  = (int) ($rak['kegiatan_id'] ?? 0);
            $skid = (int) ($rak['sub_kegiatan_id'] ?? 0);
            $rid  = (int) $rak['rekening_id'];
            $bulan = (int) $rak['bulan'];
            $val   = (float) $rak['nilai_rak'];

            if (!isset($hierarchy[$pKey])) {
                $hierarchy[$pKey] = [
                    'kode'    => $rak['kode_program'] ?? '',
                    'nama'    => $rak['nama_program'] ?? 'Tidak Diketahui',
                    'months'  => array_fill(1, 12, 0),
                    'total'   => 0,
                    'children' => [],
                ];
            }
            if (!isset($hierarchy[$pKey]['children'][$kid])) {
                $hierarchy[$pKey]['children'][$kid] = [
                    'kode'    => $rak['kode_kegiatan'] ?? '',
                    'nama'    => $rak['nama_kegiatan'] ?? 'Tidak Diketahui',
                    'months'  => array_fill(1, 12, 0),
                    'total'   => 0,
                    'children' => [],
                ];
            }
            if (!isset($hierarchy[$pKey]['children'][$kid]['children'][$skid])) {
                $hierarchy[$pKey]['children'][$kid]['children'][$skid] = [
                    'kode'    => $rak['kode_sub_kegiatan'] ?? '',
                    'nama'    => $rak['nama_sub_kegiatan'] ?? 'Tidak Diketahui',
                    'months'  => array_fill(1, 12, 0),
                    'total'   => 0,
                    'children' => [],
                ];
            }
            if (!isset($hierarchy[$pKey]['children'][$kid]['children'][$skid]['children'][$rid])) {
                $hierarchy[$pKey]['children'][$kid]['children'][$skid]['children'][$rid] = [
                    'kode'   => $rak['kode_rekening'] ?? '',
                    'nama'   => $rak['nama_rekening'] ?? 'Tidak Diketahui',
                    'months' => array_fill(1, 12, 0),
                    'total'  => 0,
                ];
            }

            $rekNode = &$hierarchy[$pKey]['children'][$kid]['children'][$skid]['children'][$rid];
            if ($bulan >= 1 && $bulan <= 12) {
                $rekNode['months'][$bulan] += $val;
            }
            $rekNode['total'] += $val;
            unset($rekNode);

            if ($bulan >= 1 && $bulan <= 12) {
                $hierarchy[$pKey]['children'][$kid]['children'][$skid]['months'][$bulan] += $val;
            }
            $hierarchy[$pKey]['children'][$kid]['children'][$skid]['total'] += $val;

            if ($bulan >= 1 && $bulan <= 12) {
                $hierarchy[$pKey]['children'][$kid]['months'][$bulan] += $val;
            }
            $hierarchy[$pKey]['children'][$kid]['total'] += $val;

            if ($bulan >= 1 && $bulan <= 12) {
                $hierarchy[$pKey]['months'][$bulan] += $val;
            }
            $hierarchy[$pKey]['total'] += $val;
        }
        unset($pKey, $kid, $skid, $rid);

        // Sort per level by kode (query sudah terurut, pengaman bila filter parsial)
        uasort($hierarchy, fn($a, $b) => strcmp($a['kode'], $b['kode']));
        foreach ($hierarchy as &$progNode) {
            uasort($progNode['children'], fn($a, $b) => strcmp($a['kode'], $b['kode']));
            foreach ($progNode['children'] as &$kegNode) {
                uasort($kegNode['children'], fn($a, $b) => strcmp($a['kode'], $b['kode']));
                foreach ($kegNode['children'] as &$skNode) {
                    uasort($skNode['children'], fn($a, $b) => strcmp($a['kode'], $b['kode']));
                }
                unset($skNode);
            }
            unset($kegNode);
        }
        unset($progNode);

        $bulanNamesLong = [1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',
                           7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'];
        $lastCol = 'N'; // A = hierarki, B..M = Jan..Des, N = Total RAK

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('RAK');

        // ---- Column widths ----
        $sheet->getColumnDimension('A')->setWidth(55);
        for ($m = 1; $m <= 12; $m++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex(1 + $m))->setWidth(15);
        }
        $sheet->getColumnDimension($lastCol)->setWidth(20);

        // ---- Row 1: Title ----
        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->setCellValue('A1', 'RENCANA ANGGARAN KAS (RAK)' . ($filterTahun !== null ? ' — TAHUN ' . $filterTahun : ''));
        $sheet->getStyle('A1')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '1E3A5F']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(26);

        // ---- Row 2: Subtitle ----
        $sheet->mergeCells("A2:{$lastCol}2");
        $sheet->setCellValue('A2', 'CDK WILAYAH BOJONEGORO');
        $sheet->getStyle('A2')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 11, 'color' => ['rgb' => '374151']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(2)->setRowHeight(20);

        // ---- Row 3: Filter info ----
        $filterInfo = ['Tahun: ' . ($filterTahun !== null ? $filterTahun : 'Semua')];
        if ($filterKegiatan !== null) {
            $keg = $this->kegiatanModel->getById($filterKegiatan);
            if ($keg) $filterInfo[] = 'Kegiatan: ' . ($keg['kode_kegiatan'] . ' - ' . $keg['nama_kegiatan']);
        }
        if ($filterSubKegiatan !== null) {
            $subKeg = $this->subKegiatanModel->getById($filterSubKegiatan);
            if ($subKeg) $filterInfo[] = 'Sub Kegiatan: ' . ($subKeg['kode_sub_kegiatan'] . ' - ' . $subKeg['nama_sub_kegiatan']);
        }
        $filterInfo[] = 'Diunduh pada: ' . date('d/m/Y H:i:s');
        $sheet->mergeCells("A3:{$lastCol}3");
        $sheet->setCellValue('A3', implode(' | ', $filterInfo));
        $sheet->getStyle('A3')->applyFromArray([
            'font'      => ['italic' => true, 'size' => 9, 'color' => ['rgb' => '64748B']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(3)->setRowHeight(16);

        // ---- Row 4: spacer ----
        $sheet->getRowDimension(4)->setRowHeight(6);

        // ---- Row 5: Column headers ----
        $sheet->setCellValue('A5', 'PROGRAM / KEGIATAN / SUB KEGIATAN / REKENING');
        for ($m = 1; $m <= 12; $m++) {
            $colLetter = Coordinate::stringFromColumnIndex(1 + $m); // B..M
            $sheet->setCellValue("{$colLetter}5", $bulanNamesLong[$m]);
        }
        $sheet->setCellValue("{$lastCol}5", 'TOTAL RAK');

        $headerStyle = [
            'font'      => ['bold' => true, 'size' => 9, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0369A1']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '075985']]],
        ];
        $sheet->getStyle("A5:{$lastCol}5")->applyFromArray($headerStyle);
        $sheet->getRowDimension(5)->setRowHeight(22);

        // ---- Row 6 onwards: hierarchy ----
        $currentRow = 6;
        $numFmt = '#,##0';
        $progRows = []; // baris level program (untuk total bawah)

        if (empty($hierarchy)) {
            $sheet->setCellValue("A{$currentRow}", 'Tidak ada data RAK sesuai filter yang dipilih');
            $sheet->mergeCells("A{$currentRow}:{$lastCol}{$currentRow}");
            $sheet->getStyle("A{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("A{$currentRow}:{$lastCol}{$currentRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('CCCCCC');
            $currentRow++;
        }

        foreach ($hierarchy as $progNode) {
            $progRows[] = $currentRow;
            $this->writeRakRow($sheet, $currentRow, $progNode, 'program', $numFmt);
            $currentRow++;

            foreach ($progNode['children'] as $kegNode) {
                $this->writeRakRow($sheet, $currentRow, $kegNode, 'kegiatan', $numFmt);
                $currentRow++;

                foreach ($kegNode['children'] as $skNode) {
                    $this->writeRakRow($sheet, $currentRow, $skNode, 'sub_kegiatan', $numFmt);
                    $currentRow++;

                    foreach ($skNode['children'] as $rekNode) {
                        $this->writeRakRow($sheet, $currentRow, $rekNode, 'rekening', $numFmt);
                        $currentRow++;
                    }
                }
            }
        }

        // ---- Total row: SUM hanya dari baris level program ----
        $sheet->setCellValue("A{$currentRow}", 'TOTAL KESELURUHAN');
        if (!empty($progRows)) {
            for ($m = 1; $m <= 12; $m++) {
                $colLetter = Coordinate::stringFromColumnIndex(1 + $m);
                $refs = array_map(fn($r) => "{$colLetter}{$r}", $progRows);
                $sheet->setCellValue("{$colLetter}{$currentRow}", '=' . implode('+', $refs));
                $sheet->getStyle("{$colLetter}{$currentRow}")->getNumberFormat()->setFormatCode($numFmt);
                $sheet->getStyle("{$colLetter}{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            }
            $refsTotal = array_map(fn($r) => "{$lastCol}{$r}", $progRows);
            $sheet->setCellValue("{$lastCol}{$currentRow}", '=' . implode('+', $refsTotal));
        } else {
            for ($m = 1; $m <= 12; $m++) {
                $sheet->setCellValue(Coordinate::stringFromColumnIndex(1 + $m) . $currentRow, 0);
            }
            $sheet->setCellValue("{$lastCol}{$currentRow}", 0);
        }
        $sheet->getStyle("{$lastCol}{$currentRow}")->getNumberFormat()->setFormatCode($numFmt);
        $sheet->getStyle("{$lastCol}{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        $totalStyle = [
            'font'      => ['bold' => true, 'size' => 9, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A5F']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => 'FFFFFF']]],
        ];
        $sheet->getStyle("A{$currentRow}:{$lastCol}{$currentRow}")->applyFromArray($totalStyle);
        $sheet->getRowDimension($currentRow)->setRowHeight(20);

        // ---- Freeze header ----
        $sheet->freezePane('A6');

        return $spreadsheet;
    }

    /**
     * Tulis satu baris hierarki RAK (indentasi + warna per level,
     * selaras dengan gaya Laporan Realisasi).
     */
    private function writeRakRow(
        \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet,
        int $row,
        array $node,
        string $type,
        string $numFmt
    ): void {
        $indent = match($type) {
            'program'      => '',
            'kegiatan'     => '  ',
            'sub_kegiatan' => '    ',
            'rekening'     => '      ',
            default        => '',
        };

        $label = $indent . ($node['kode'] ? $node['kode'] . '   ' : '') . $node['nama'];

        $sheet->setCellValue("A{$row}", $label);
        for ($m = 1; $m <= 12; $m++) {
            $colLetter = Coordinate::stringFromColumnIndex(1 + $m); // B..M
            $sheet->setCellValue("{$colLetter}{$row}", $node['months'][$m] ?? 0);
            $sheet->getStyle("{$colLetter}{$row}")->getNumberFormat()->setFormatCode($numFmt);
            $sheet->getStyle("{$colLetter}{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }
        $sheet->setCellValue("N{$row}", $node['total'] ?? 0);
        $sheet->getStyle("N{$row}")->getNumberFormat()->setFormatCode($numFmt);
        $sheet->getStyle("N{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        $bgColor = match($type) {
            'program'      => 'D6E4F0',  // Light blue - Program
            'kegiatan'     => 'E8F5E9',  // Light green - Kegiatan
            'sub_kegiatan' => 'FFF9E6',  // Light yellow - Sub Kegiatan
            'rekening'     => 'FFFFFF',  // White - Rekening
            default        => 'FFFFFF',
        };

        $bold = in_array($type, ['program', 'kegiatan']);
        $fontSize = $type === 'program' ? 10 : 9;

        $sheet->getStyle("A{$row}:N{$row}")->applyFromArray([
            'font'      => ['bold' => $bold, 'size' => $fontSize, 'color' => ['rgb' => '000000']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bgColor]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CCCCCC']]],
        ]);
        $sheet->getRowDimension($row)->setRowHeight(18);
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

