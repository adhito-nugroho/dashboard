<?php

namespace App\Controllers;

use App\Models\Pagu;
use App\Models\Rak;
use App\Models\Transaksi;
use App\Models\Seksi;
use App\Models\Program;
use App\Models\Kegiatan;
use App\Models\SubKegiatan;

class DashboardController {
    private Pagu $paguModel;
    private Rak $rakModel;
    private Transaksi $transaksiModel;
    private Seksi $seksiModel;
    private Program $programModel;
    private Kegiatan $kegiatanModel;
    private SubKegiatan $subKegiatanModel;
    
    public function __construct(
        Pagu $paguModel,
        Rak $rakModel,
        Transaksi $transaksiModel,
        Seksi $seksiModel,
        Program $programModel,
        Kegiatan $kegiatanModel,
        SubKegiatan $subKegiatanModel
    ) {
        $this->paguModel = $paguModel;
        $this->rakModel = $rakModel;
        $this->transaksiModel = $transaksiModel;
        $this->seksiModel = $seksiModel;
        $this->programModel = $programModel;
        $this->kegiatanModel = $kegiatanModel;
        $this->subKegiatanModel = $subKegiatanModel;
    }
    
    /**
     * Display dashboard
     */
    public function index(): void {
        $tahun = isset($_GET['tahun']) ? (int) $_GET['tahun'] : (int) date('Y');
        
        // Get filter parameters
        $filters = [
            'seksi_id' => isset($_GET['seksi_id']) && $_GET['seksi_id'] !== '' ? (int) $_GET['seksi_id'] : null,
            'program_id' => isset($_GET['program_id']) && $_GET['program_id'] !== '' ? (int) $_GET['program_id'] : null,
            'kegiatan_id' => isset($_GET['kegiatan_id']) && $_GET['kegiatan_id'] !== '' ? (int) $_GET['kegiatan_id'] : null,
            'sub_kegiatan_id' => isset($_GET['sub_kegiatan_id']) && $_GET['sub_kegiatan_id'] !== '' ? (int) $_GET['sub_kegiatan_id'] : null,
        ];
        
        // Get filter options
        $filterOptions = $this->getFilterOptions($filters);
        
        // Get statistics with filters
        $stats = $this->getStatistics($tahun, $filters);
        
        // Get monthly data for chart with filters
        $monthlyData = $this->getMonthlyData($tahun, $filters);

        // Get monthly absorption details grouped by sub kegiatan and rekening
        $monthlyAbsorptionDetails = $this->getMonthlyAbsorptionDetails($tahun, $filters);
        
        // Get serapan percentage with filters
        $serapan = $this->getSerapan($tahun, $filters);
        
        // Get breakdown data
        $breakdownData = $this->getBreakdownData($tahun, $filters);
        
        // Get hierarchical data
        $hierarchicalData = $this->getHierarchicalData($tahun, $filters);

        // Get deviation details (sub kegiatan & rekening yang tidak sesuai RAK)
        $deviationDetails = $this->getDeviationDetails($tahun, $filters);

        // Get semester rekap (sisa dana per semester per rekening)
        $semesterRekap = $this->getSemesterRekapData($tahun, $filters);

        // Page Title
        $pageTitle = 'CDK Wilayah Bojonegoro';
        $activePage = 'dashboard';
        $viewFile = __DIR__ . '/../../views/dashboard/index.php';
        
        include __DIR__ . '/../../views/layout.php';
    }
    
    /**
     * Get filter options based on current filters
     * 
     * @param array $filters
     * @return array
     */
    private function getFilterOptions(array $filters): array {
        // Get all seksi (not filtered)
        $seksiOptions = $this->seksiModel->getAll();
        
        // Get programs - filter by seksi if selected
        $programOptions = [];
        if (!empty($filters['seksi_id'])) {
            // Get programs that have sub_kegiatan with this seksi_id
            $allPrograms = $this->programModel->getAll();
            $allSubKegiatan = $this->subKegiatanModel->getAll();
            
            // Find sub_kegiatan with this seksi_id
            $validSubKegiatanIds = array_column(
                array_filter($allSubKegiatan, function($sk) use ($filters) {
                    return $sk['seksi_id'] == $filters['seksi_id'];
                }),
                'id'
            );
            
            // Find kegiatan that have these sub_kegiatan
            $allKegiatan = $this->kegiatanModel->getAll();
            $validKegiatanIds = array_unique(array_column(
                array_filter($allSubKegiatan, function($sk) use ($validSubKegiatanIds) {
                    return in_array($sk['id'], $validSubKegiatanIds);
                }),
                'kegiatan_id'
            ));
            
            // Find programs that have these kegiatan
            $validProgramIds = array_unique(array_column(
                array_filter($allKegiatan, function($k) use ($validKegiatanIds) {
                    return in_array($k['id'], $validKegiatanIds);
                }),
                'program_id'
            ));
            
            // Filter programs
            $programOptions = array_filter($allPrograms, function($p) use ($validProgramIds) {
                return in_array($p['id'], $validProgramIds);
            });
        } else {
            $programOptions = $this->programModel->getAll();
        }
        
        // Get kegiatan - filter by program if selected
        $kegiatanOptions = [];
        if (!empty($filters['program_id'])) {
            $kegiatanOptions = $this->kegiatanModel->getAll();
            $kegiatanOptions = array_filter($kegiatanOptions, function($k) use ($filters) {
                return $k['program_id'] == $filters['program_id'];
            });
        } elseif (!empty($filters['seksi_id'])) {
            // Filter by seksi
            $allKegiatan = $this->kegiatanModel->getAll();
            $allSubKegiatan = $this->subKegiatanModel->getAll();
            
            $validSubKegiatanIds = array_column(
                array_filter($allSubKegiatan, function($sk) use ($filters) {
                    return $sk['seksi_id'] == $filters['seksi_id'];
                }),
                'id'
            );
            
            $validKegiatanIds = array_unique(array_column(
                array_filter($allSubKegiatan, function($sk) use ($validSubKegiatanIds) {
                    return in_array($sk['id'], $validSubKegiatanIds);
                }),
                'kegiatan_id'
            ));
            
            $kegiatanOptions = array_filter($allKegiatan, function($k) use ($validKegiatanIds) {
                return in_array($k['id'], $validKegiatanIds);
            });
        } else {
            $kegiatanOptions = $this->kegiatanModel->getAll();
        }
        
        // Get sub_kegiatan - filter by kegiatan or seksi if selected
        $subKegiatanOptions = [];
        if (!empty($filters['kegiatan_id'])) {
            $allSubKegiatan = $this->subKegiatanModel->getAll();
            $subKegiatanOptions = array_filter($allSubKegiatan, function($sk) use ($filters) {
                return $sk['kegiatan_id'] == $filters['kegiatan_id'];
            });
        } elseif (!empty($filters['program_id'])) {
            // Filter by program
            $allSubKegiatan = $this->subKegiatanModel->getAll();
            $allKegiatan = $this->kegiatanModel->getAll();
            
            $validKegiatanIds = array_column(
                array_filter($allKegiatan, function($k) use ($filters) {
                    return $k['program_id'] == $filters['program_id'];
                }),
                'id'
            );
            
            $subKegiatanOptions = array_filter($allSubKegiatan, function($sk) use ($validKegiatanIds) {
                return in_array($sk['kegiatan_id'], $validKegiatanIds);
            });
        } elseif (!empty($filters['seksi_id'])) {
            // Filter by seksi
            $allSubKegiatan = $this->subKegiatanModel->getAll();
            $subKegiatanOptions = array_filter($allSubKegiatan, function($sk) use ($filters) {
                return $sk['seksi_id'] == $filters['seksi_id'];
            });
        } else {
            $subKegiatanOptions = $this->subKegiatanModel->getAll();
        }
        
        return [
            'seksi' => array_values($seksiOptions),
            'program' => array_values($programOptions),
            'kegiatan' => array_values($kegiatanOptions),
            'sub_kegiatan' => array_values($subKegiatanOptions)
        ];
    }
    
    /**
     * Get statistics for a year with filters
     * 
     * @param int $tahun
     * @param array $filters
     * @return array
     */
    private function getStatistics(int $tahun, array $filters = []): array {
        // Get all pagu for the year
        $pagus = $this->paguModel->getAll();
        $totalPagu = 0;
        $totalRak = 0;
        $totalRealisasi = 0;
        
        foreach ($pagus as $pagu) {
            if ($pagu['tahun'] == $tahun && $this->matchesFilters($pagu, $filters)) {
                $totalPagu += (float) $pagu['nilai_pagu'];
                
                // Get total RAK for this rekening and year
                $rakTotal = $this->rakModel->getTotalByRekeningAndYear($pagu['rekening_id'], $tahun);
                $totalRak += $rakTotal;
                
                // Get total transactions for this rekening and year
                $transaksiTotal = $this->transaksiModel->getTotalByRekeningAndYear($pagu['rekening_id'], $tahun);
                $totalRealisasi += $transaksiTotal;
            }
        }
        
        $sisaAnggaran = $totalPagu - $totalRealisasi;
        $percentage = $totalPagu > 0 ? ($totalRealisasi / $totalPagu) * 100 : 0;
        
        return [
            'tahun' => $tahun,
            'total_pagu' => $totalPagu,
            'total_rak' => $totalRak,
            'total_realisasi' => $totalRealisasi,
            'sisa_anggaran' => $sisaAnggaran,
            'percentage' => $percentage
        ];
    }
    
    /**
     * Get monthly data for chart
     * 
     * @param int $tahun
     * @param array $filters
     * @return array
     */
    private function getMonthlyData(int $tahun, array $filters = []): array {
        $monthlyRak = array_fill(1, 12, 0);
        $monthlyRealisasi = array_fill(1, 12, 0);
        $monthlyAlerts = [];
        
        // Get all pagu for the year
        $pagus = $this->paguModel->getAll();
        
        foreach ($pagus as $pagu) {
            if ($pagu['tahun'] == $tahun && $this->matchesFilters($pagu, $filters)) {
                // Get RAK data for each month
                $raks = $this->rakModel->getByRekeningAndYear($pagu['rekening_id'], $tahun);
                foreach ($raks as $rak) {
                    $monthlyRak[$rak['bulan']] += (float) $rak['nilai_rak'];
                }
                
                // Get transactions for each month
                $transaksis = $this->transaksiModel->getByRekeningAndYear($pagu['rekening_id'], $tahun);
                foreach ($transaksis as $transaksi) {
                    $bulan = (int) date('n', strtotime($transaksi['tanggal']));
                    $monthlyRealisasi[$bulan] += (float) $transaksi['nilai'];
                }
            }
        }
        
        // Check for alerts: Realisasi > RAK (over) atau Realisasi < RAK (under)
        for ($bulan = 1; $bulan <= 12; $bulan++) {
            // Hanya cek bulan yang sudah lewat atau sekarang
            $currentMonth = (int) date('n');
            $currentYear  = (int) date('Y');
            // Jika tahun sekarang, hanya cek s/d bulan saat ini
            // Jika tahun lalu, cek semua 12 bulan
            $isRelevantMonth = true;
            if ($currentYear == $tahun && $bulan > $currentMonth) {
                $isRelevantMonth = false;
            }

            if (!$isRelevantMonth) continue;

            if ($monthlyRak[$bulan] > 0) {
                if ($monthlyRealisasi[$bulan] > $monthlyRak[$bulan]) {
                    $monthlyAlerts[] = [
                        'type'       => 'over',
                        'bulan'      => $bulan,
                        'rak'        => $monthlyRak[$bulan],
                        'realisasi'  => $monthlyRealisasi[$bulan]
                    ];
                } elseif ($monthlyRealisasi[$bulan] < $monthlyRak[$bulan]) {
                    $monthlyAlerts[] = [
                        'type'       => 'under',
                        'bulan'      => $bulan,
                        'rak'        => $monthlyRak[$bulan],
                        'realisasi'  => $monthlyRealisasi[$bulan]
                    ];
                }
            }
        }
        
        return [
            'rak' => $monthlyRak,
            'realisasi' => $monthlyRealisasi,
            'alerts' => $monthlyAlerts
        ];
    }
    
    /**
     * Get serapan (absorption) percentage
     * 
     * @param int $tahun
     * @param array $filters
     * @return float
     */
    private function getSerapan(int $tahun, array $filters = []): float {
        $totalPagu = 0;
        $totalRealisasi = 0;
        
        $pagus = $this->paguModel->getAll();
        foreach ($pagus as $pagu) {
            if ($pagu['tahun'] == $tahun && $this->matchesFilters($pagu, $filters)) {
                $totalPagu += (float) $pagu['nilai_pagu'];
                $transaksiTotal = $this->transaksiModel->getTotalByRekeningAndYear($pagu['rekening_id'], $tahun);
                $totalRealisasi += $transaksiTotal;
            }
        }
        
        return $totalPagu > 0 ? ($totalRealisasi / $totalPagu) * 100 : 0;
    }
    
    /**
     * Check if pagu matches filters
     * 
     * @param array $pagu
     * @param array $filters
     * @return bool
     */
    private function matchesFilters(array $pagu, array $filters): bool {
        // Check seksi filter
        if (!empty($filters['seksi_id'])) {
            if (!isset($pagu['sub_kegiatan_seksi_id']) || $pagu['sub_kegiatan_seksi_id'] != $filters['seksi_id']) {
                return false;
            }
        }
        
        // Check program filter
        if (!empty($filters['program_id'])) {
            if (!isset($pagu['program_id']) || $pagu['program_id'] != $filters['program_id']) {
                return false;
            }
        }
        
        // Check kegiatan filter
        if (!empty($filters['kegiatan_id'])) {
            if (!isset($pagu['kegiatan_id']) || $pagu['kegiatan_id'] != $filters['kegiatan_id']) {
                return false;
            }
        }
        
        // Check sub_kegiatan filter
        if (!empty($filters['sub_kegiatan_id'])) {
            if (!isset($pagu['sub_kegiatan_id']) || $pagu['sub_kegiatan_id'] != $filters['sub_kegiatan_id']) {
                return false;
            }
        }
        
        return true;
    }
    
    
    /**
     * Get breakdown data for visualization
     * 
     * @param int $tahun
     * @param array $filters
     * @return array
     */
    private function getBreakdownData(int $tahun, array $filters): array {
        $pagus = $this->paguModel->getAll();
        $breakdown = [];
        $bulanBerjalan = $tahun == (int) date('Y') ? (int) date('n') : 12;
        
        foreach ($pagus as $pagu) {
            if ($pagu['tahun'] == $tahun && $this->matchesFilters($pagu, $filters)) {
                $rid = (int) $pagu['rekening_id'];
                $paguVal = (float) $pagu['nilai_pagu'];
                $realisasiVal = $this->transaksiModel->getTotalByRekeningAndYear($rid, $tahun);
                
                // Hitung RAK kumulatif s/d bulan berjalan untuk rekening ini
                $rakKumulatifVal = 0;
                $raks = $this->rakModel->getByRekeningAndYear($rid, $tahun);
                foreach ($raks as $rak) {
                    if ((int)$rak['bulan'] <= $bulanBerjalan) {
                        $rakKumulatifVal += (float) $rak['nilai_rak'];
                    }
                }

                // Determine breakdown level based on filters
                if (empty($filters['seksi_id'])) {
                    // Group by seksi
                    if (isset($pagu['sub_kegiatan_seksi_id'])) {
                        $seksi = $this->seksiModel->getById($pagu['sub_kegiatan_seksi_id']);
                        if ($seksi) {
                            $key = $seksi['nama_seksi'];
                            if (!isset($breakdown[$key])) {
                                $breakdown[$key] = ['pagu' => 0, 'realisasi' => 0, 'rak_kumulatif' => 0];
                            }
                            $breakdown[$key]['pagu'] += $paguVal;
                            $breakdown[$key]['realisasi'] += $realisasiVal;
                            $breakdown[$key]['rak_kumulatif'] += $rakKumulatifVal;
                        }
                    }
                } elseif (empty($filters['program_id'])) {
                    // Group by program - data already in pagu
                    $key = $pagu['nama_program'] ?? 'Unknown';
                    if (!isset($breakdown[$key])) {
                        $breakdown[$key] = ['pagu' => 0, 'realisasi' => 0, 'rak_kumulatif' => 0];
                    }
                    $breakdown[$key]['pagu'] += $paguVal;
                    $breakdown[$key]['realisasi'] += $realisasiVal;
                    $breakdown[$key]['rak_kumulatif'] += $rakKumulatifVal;
                } elseif (empty($filters['kegiatan_id'])) {
                    // Group by kegiatan - data already in pagu
                    $key = $pagu['nama_kegiatan'] ?? 'Unknown';
                    if (!isset($breakdown[$key])) {
                        $breakdown[$key] = ['pagu' => 0, 'realisasi' => 0, 'rak_kumulatif' => 0];
                    }
                    $breakdown[$key]['pagu'] += $paguVal;
                    $breakdown[$key]['realisasi'] += $realisasiVal;
                    $breakdown[$key]['rak_kumulatif'] += $rakKumulatifVal;
                } else {
                    // Group by sub_kegiatan - data already in pagu
                    $key = $pagu['nama_sub_kegiatan'] ?? 'Unknown';
                    if (!isset($breakdown[$key])) {
                        $breakdown[$key] = ['pagu' => 0, 'realisasi' => 0, 'rak_kumulatif' => 0];
                    }
                    $breakdown[$key]['pagu'] += $paguVal;
                    $breakdown[$key]['realisasi'] += $realisasiVal;
                    $breakdown[$key]['rak_kumulatif'] += $rakKumulatifVal;
                }
            }
        }
        
        return $breakdown;
    }
    
    /**
     * Get hierarchical structure of budget
     * 
     * @param int $tahun
     * @param array $filters
     * @return array
     */
    private function getHierarchicalData(int $tahun, array $filters): array {
        $pagus = $this->paguModel->getAll();
        
        // Fetch seksi data for mapping
        $seksis = $this->seksiModel->getAll();
        $seksiMap = [];
        $seksiKodeMap = [];
        foreach ($seksis as $s) {
            $seksiMap[$s['id']] = $s['nama_seksi'];
            $seksiKodeMap[$s['id']] = $s['kode_seksi'] ?? '';
        }
        
        $hierarchy = [];
        
        foreach ($pagus as $pagu) {
            if ($pagu['tahun'] == $tahun && $this->matchesFilters($pagu, $filters)) {
                $sid = $pagu['sub_kegiatan_seksi_id'] ?? 0;
                $pid = $pagu['program_id'];
                $kid = $pagu['kegiatan_id'];
                $skid = $pagu['sub_kegiatan_id'];
                $rid = $pagu['rekening_id'];
                
                $realisasiRek = $this->transaksiModel->getTotalByRekeningAndYear($rid, $tahun);
                $paguRek = (float) $pagu['nilai_pagu'];
                
                if (!isset($hierarchy[$sid])) {
                    $hierarchy[$sid] = [
                        'type' => 'Seksi',
                        'kode' => $seksiKodeMap[$sid] ?? '-',
                        'nama' => $seksiMap[$sid] ?? 'Tidak Diketahui',
                        'pagu' => 0,
                        'realisasi' => 0,
                        'children' => []
                    ];
                }
                
                if (!isset($hierarchy[$sid]['children'][$pid])) {
                    $hierarchy[$sid]['children'][$pid] = [
                        'type' => 'Program',
                        'kode' => $pagu['kode_program'] ?? '',
                        'nama' => $pagu['nama_program'] ?? '',
                        'pagu' => 0,
                        'realisasi' => 0,
                        'children' => []
                    ];
                }
                
                if (!isset($hierarchy[$sid]['children'][$pid]['children'][$kid])) {
                    $hierarchy[$sid]['children'][$pid]['children'][$kid] = [
                        'type' => 'Kegiatan',
                        'kode' => $pagu['kode_kegiatan'] ?? '',
                        'nama' => $pagu['nama_kegiatan'] ?? '',
                        'pagu' => 0,
                        'realisasi' => 0,
                        'children' => []
                    ];
                }
                
                if (!isset($hierarchy[$sid]['children'][$pid]['children'][$kid]['children'][$skid])) {
                    $hierarchy[$sid]['children'][$pid]['children'][$kid]['children'][$skid] = [
                        'type' => 'Sub Kegiatan',
                        'kode' => $pagu['kode_sub_kegiatan'] ?? '',
                        'nama' => $pagu['nama_sub_kegiatan'] ?? '',
                        'pagu' => 0,
                        'realisasi' => 0,
                        'children' => []
                    ];
                }
                
                $hierarchy[$sid]['children'][$pid]['children'][$kid]['children'][$skid]['children'][$rid] = [
                    'type' => 'Belanja',
                    'kode' => $pagu['kode_rekening'] ?? '',
                    'nama' => $pagu['nama_rekening'] ?? '',
                    'pagu' => $paguRek,
                    'realisasi' => $realisasiRek,
                    'children' => []
                ];
                
                // Accumulate totals upwards
                $hierarchy[$sid]['children'][$pid]['children'][$kid]['children'][$skid]['pagu'] += $paguRek;
                $hierarchy[$sid]['children'][$pid]['children'][$kid]['children'][$skid]['realisasi'] += $realisasiRek;
                
                $hierarchy[$sid]['children'][$pid]['children'][$kid]['pagu'] += $paguRek;
                $hierarchy[$sid]['children'][$pid]['children'][$kid]['realisasi'] += $realisasiRek;
                
                $hierarchy[$sid]['children'][$pid]['pagu'] += $paguRek;
                $hierarchy[$sid]['children'][$pid]['realisasi'] += $realisasiRek;
                
                $hierarchy[$sid]['pagu'] += $paguRek;
                $hierarchy[$sid]['realisasi'] += $realisasiRek;
            }
        }
        
        // Sort keys by kode
        uasort($hierarchy, fn($a, $b) => strcmp($a['kode'], $b['kode']));
        foreach($hierarchy as &$s) {
            uasort($s['children'], fn($a, $b) => strcmp($a['kode'], $b['kode']));
            foreach($s['children'] as &$p) {
                uasort($p['children'], fn($a, $b) => strcmp($a['kode'], $b['kode']));
                foreach($p['children'] as &$k) {
                    uasort($k['children'], fn($a, $b) => strcmp($a['kode'], $b['kode']));
                    foreach($k['children'] as &$sk) {
                        uasort($sk['children'], fn($a, $b) => strcmp($a['kode'], $b['kode']));
                    }
                }
            }
        }
        
        return $hierarchy;
    }

    /**
     * Get monthly absorption grouped by sub kegiatan and rekening.
     *
     * @param int $tahun
     * @param array $filters
     * @return array
     */
    private function getMonthlyAbsorptionDetails(int $tahun, array $filters): array {
        $pagus = $this->paguModel->getAll();
        $subKegiatanMap = [];
        $totals = [
            'pagu' => 0,
            'realisasi' => 0,
            'rekening_count' => 0,
            'months' => array_fill(1, 12, 0),
        ];

        foreach ($pagus as $pagu) {
            if ($pagu['tahun'] != $tahun || !$this->matchesFilters($pagu, $filters)) {
                continue;
            }

            $skid = (int) $pagu['sub_kegiatan_id'];
            $rid = (int) $pagu['rekening_id'];
            $paguRekening = (float) $pagu['nilai_pagu'];

            if (!isset($subKegiatanMap[$skid])) {
                $subKegiatanMap[$skid] = [
                    'kode' => $pagu['kode_sub_kegiatan'] ?? '',
                    'nama' => $pagu['nama_sub_kegiatan'] ?? 'Tidak Diketahui',
                    'pagu' => 0,
                    'realisasi' => 0,
                    'months' => array_fill(1, 12, 0),
                    'rekening' => [],
                ];
            }

            $rekeningMonths = array_fill(1, 12, 0);
            $transaksis = $this->transaksiModel->getByRekeningAndYear($rid, $tahun);
            foreach ($transaksis as $transaksi) {
                $bulan = (int) date('n', strtotime($transaksi['tanggal']));
                if ($bulan >= 1 && $bulan <= 12) {
                    $rekeningMonths[$bulan] += (float) $transaksi['nilai'];
                }
            }

            $realisasiRekening = array_sum($rekeningMonths);

            $subKegiatanMap[$skid]['rekening'][$rid] = [
                'kode' => $pagu['kode_rekening'] ?? '',
                'nama' => $pagu['nama_rekening'] ?? 'Tidak Diketahui',
                'pagu' => $paguRekening,
                'realisasi' => $realisasiRekening,
                'months' => $rekeningMonths,
            ];

            $subKegiatanMap[$skid]['pagu'] += $paguRekening;
            $subKegiatanMap[$skid]['realisasi'] += $realisasiRekening;
            $totals['pagu'] += $paguRekening;
            $totals['realisasi'] += $realisasiRekening;
            $totals['rekening_count']++;

            for ($bulan = 1; $bulan <= 12; $bulan++) {
                $subKegiatanMap[$skid]['months'][$bulan] += $rekeningMonths[$bulan];
                $totals['months'][$bulan] += $rekeningMonths[$bulan];
            }
        }

        uasort($subKegiatanMap, fn($a, $b) => strcmp($a['kode'], $b['kode']));
        foreach ($subKegiatanMap as &$subKegiatan) {
            uasort($subKegiatan['rekening'], fn($a, $b) => strcmp($a['kode'], $b['kode']));
        }

        return [
            'sub_kegiatan' => $subKegiatanMap,
            'totals' => $totals,
        ];
    }

    /**
     * Get deviation details: sub kegiatan & rekening that deviate from RAK plan
     * Returns list of sub_kegiatan with rekening that have realisasi != rak
     *
     * @param int $tahun
     * @param array $filters
     * @return array
     */
    private function getDeviationDetails(int $tahun, array $filters): array {
        $pagus = $this->paguModel->getAll();
        $currentMonth = (int) date('n');
        $currentYear  = (int) date('Y');

        $subKegiatanMap = [];

        foreach ($pagus as $pagu) {
            if ($pagu['tahun'] != $tahun || !$this->matchesFilters($pagu, $filters)) continue;

            $skid   = $pagu['sub_kegiatan_id'];
            $rid    = $pagu['rekening_id'];
            $skNama = $pagu['nama_sub_kegiatan'] ?? 'Unknown';
            $skKode = $pagu['kode_sub_kegiatan'] ?? '';
            $rNama  = $pagu['nama_rekening']  ?? 'Unknown';
            $rKode  = $pagu['kode_rekening']  ?? '';

            // Get RAK per month for this rekening
            $raks = $this->rakModel->getByRekeningAndYear($rid, $tahun);
            $rakByMonth = [];
            foreach ($raks as $r) {
                $rakByMonth[(int)$r['bulan']] = (float)$r['nilai_rak'];
            }

            // Get realisasi per month
            $transaksis = $this->transaksiModel->getByRekeningAndYear($rid, $tahun);
            $realisasiByMonth = [];
            foreach ($transaksis as $t) {
                $b = (int) date('n', strtotime($t['tanggal']));
                $realisasiByMonth[$b] = ($realisasiByMonth[$b] ?? 0) + (float)$t['nilai'];
            }

            // Rekening yang sudah ada penyerapan di bulan manapun (sebelum/sesudah bulan RAK)
            // tidak perlu ditampilkan — hanya tampilkan yang belum ada penyerapan sama sekali
            $totalRealisasiRekening = array_sum($realisasiByMonth);
            if ($totalRealisasiRekening > 0) continue;

            // Check deviation per month
            $rekeningDeviations = [];
            for ($b = 1; $b <= 12; $b++) {
                // Only check months up to current month (for current year)
                if ($currentYear == $tahun && $b > $currentMonth) continue;
                // Skip months with no RAK planned
                if (($rakByMonth[$b] ?? 0) == 0) continue;

                $rakVal  = $rakByMonth[$b];
                $realVal = $realisasiByMonth[$b] ?? 0;

                // Hanya tampilkan deviasi yang di bawah RAK (realisasi < rak)
                if ($realVal < $rakVal) {
                    $rekeningDeviations[] = [
                        'bulan'     => $b,
                        'rak'       => $rakVal,
                        'realisasi' => $realVal,
                        'selisih'   => $realVal - $rakVal,
                        'type'      => 'under',
                    ];
                }
            }

            if (empty($rekeningDeviations)) continue;

            if (!isset($subKegiatanMap[$skid])) {
                $subKegiatanMap[$skid] = [
                    'kode' => $skKode,
                    'nama' => $skNama,
                    'rekening' => []
                ];
            }

            $subKegiatanMap[$skid]['rekening'][$rid] = [
                'kode'       => $rKode,
                'nama'       => $rNama,
                'deviations' => $rekeningDeviations,
            ];
        }

        return $subKegiatanMap;
    }

    /**
     * Get semester rekap: sisa dana yang bisa ditarik per semester per rekening.
     * Sisa dihitung dari (Total RAK semester) - (Total Realisasi semester).
     * Semester 1 = Jan-Jun, Semester 2 = Jul-Des.
     *
     * @param int   $tahun
     * @param array $filters
     * @return array Grouped by sub kegiatan
     */
    private function getSemesterRekapData(int $tahun, array $filters): array {
        $pagus = $this->paguModel->getAll();

        $subKegiatanMap = [];
        $totals = [
            'rak_s1' => 0, 'real_s1' => 0, 'sisa_s1' => 0,
            'rak_s2' => 0, 'real_s2' => 0, 'sisa_s2' => 0,
            'carry_over' => 0,
            'total_rekening' => 0,
            'rekening_dengan_sisa_s1' => 0,
            'rekening_dengan_sisa_s2' => 0,
        ];

        foreach ($pagus as $pagu) {
            if ($pagu['tahun'] != $tahun || !$this->matchesFilters($pagu, $filters)) continue;

            $skid   = $pagu['sub_kegiatan_id'];
            $rid    = $pagu['rekening_id'];
            $skNama = $pagu['nama_sub_kegiatan'] ?? 'Unknown';
            $skKode = $pagu['kode_sub_kegiatan'] ?? '';
            $rNama  = $pagu['nama_rekening']  ?? 'Unknown';
            $rKode  = $pagu['kode_rekening']  ?? '';

            // Get RAK per month
            $raks = $this->rakModel->getByRekeningAndYear($rid, $tahun);
            $rakS1 = 0; $rakS2 = 0;
            foreach ($raks as $r) {
                $b = (int) $r['bulan'];
                $val = (float) $r['nilai_rak'];
                if ($b >= 1 && $b <= 6) $rakS1 += $val;
                elseif ($b >= 7 && $b <= 12) $rakS2 += $val;
            }

            // Get realisasi per month
            $transaksis = $this->transaksiModel->getByRekeningAndYear($rid, $tahun);
            $realS1 = 0; $realS2 = 0;
            foreach ($transaksis as $t) {
                $b = (int) date('n', strtotime($t['tanggal']));
                $val = (float) $t['nilai'];
                if ($b >= 1 && $b <= 6) $realS1 += $val;
                elseif ($b >= 7 && $b <= 12) $realS2 += $val;
            }

            $sisaS1 = $rakS1 - $realS1;
            $sisaS2 = $rakS2 - $realS2;

            // Carry-over: sisa positif S1 menambah kuota yang tersedia di S2
            // sisa_s2_efektif = RAK_s2 + max(0, sisa_s1) - realisasi_s2
            $carryOver = max(0, $sisaS1);
            $sisaS2Efektif = $rakS2 + $carryOver - $realS2;

            // Skip rekening tanpa RAK sama sekali (tidak relevan)
            if ($rakS1 == 0 && $rakS2 == 0) continue;

            if (!isset($subKegiatanMap[$skid])) {
                $subKegiatanMap[$skid] = [
                    'kode'       => $skKode,
                    'nama'       => $skNama,
                    'rekening'   => [],
                    'rak_s1'     => 0, 'real_s1' => 0, 'sisa_s1' => 0,
                    'rak_s2'     => 0, 'real_s2' => 0, 'sisa_s2' => 0,
                    'carry_over' => 0,
                ];
            }

            $subKegiatanMap[$skid]['rekening'][$rid] = [
                'kode'         => $rKode,
                'nama'         => $rNama,
                'pagu'         => (float) $pagu['nilai_pagu'],
                'rak_s1'       => $rakS1,
                'real_s1'      => $realS1,
                'sisa_s1'      => $sisaS1,
                'rak_s2'       => $rakS2,
                'real_s2'      => $realS2,
                'sisa_s2'      => $sisaS2Efektif,   // sudah termasuk carry-over dari S1
                'carry_over'   => $carryOver,        // sisa S1 yang di-carry ke S2
                'sisa_s2_raw'  => $sisaS2,           // sisa S2 murni tanpa carry-over
                'sisa_total'   => $sisaS1 + $sisaS2, // arithmetic total
            ];

            $subKegiatanMap[$skid]['rak_s1']     += $rakS1;
            $subKegiatanMap[$skid]['real_s1']    += $realS1;
            $subKegiatanMap[$skid]['sisa_s1']    += $sisaS1;
            $subKegiatanMap[$skid]['rak_s2']     += $rakS2;
            $subKegiatanMap[$skid]['real_s2']    += $realS2;
            $subKegiatanMap[$skid]['sisa_s2']    += $sisaS2Efektif;
            $subKegiatanMap[$skid]['carry_over'] = ($subKegiatanMap[$skid]['carry_over'] ?? 0) + $carryOver;

            $totals['rak_s1']  += $rakS1;
            $totals['real_s1'] += $realS1;
            $totals['sisa_s1'] += $sisaS1;
            $totals['rak_s2']  += $rakS2;
            $totals['real_s2'] += $realS2;
            $totals['sisa_s2'] += $sisaS2Efektif;
            $totals['carry_over'] = ($totals['carry_over'] ?? 0) + $carryOver;
            $totals['total_rekening']++;
            if ($sisaS1 > 0) $totals['rekening_dengan_sisa_s1']++;
            if ($sisaS2Efektif > 0) $totals['rekening_dengan_sisa_s2']++;
        }

        // Sort sub kegiatan & rekening by kode
        uasort($subKegiatanMap, fn($a, $b) => strcmp($a['kode'], $b['kode']));
        foreach ($subKegiatanMap as &$sk) {
            uasort($sk['rekening'], fn($a, $b) => strcmp($a['kode'], $b['kode']));
        }

        return [
            'sub_kegiatan' => $subKegiatanMap,
            'totals'       => $totals,
        ];
    }
}

