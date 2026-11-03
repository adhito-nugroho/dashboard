<?php

namespace App\Controllers;

use App\Models\Pagu;
use App\Models\Seksi;
use App\Services\AnalisaSisaAnggaranExportService;

/**
 * Analisa Sisa Anggaran
 *
 * Menampilkan sisa anggaran per rekening (pagu - realisasi terverifikasi),
 * dengan filter ambang sisa, seksi, pencarian, serta visualisasi distribusi.
 */
class AnalisaSisaAnggaranController
{
    private const PER_PAGE = 15;

    private Pagu $paguModel;
    private Seksi $seksiModel;

    public function __construct(Pagu $paguModel, Seksi $seksiModel)
    {
        $this->paguModel = $paguModel;
        $this->seksiModel = $seksiModel;
    }

    public function index(): void
    {
        try {
            $report = $this->buildReport($_GET);

            $tahunTersedia = $report['tahunTersedia'];
            $tahun = $report['tahun'];
            $seksiList = $report['seksiList'];
            $seksiMap = $report['seksiMap'];
            $filters = $report['filters'];
            $rows = $report['rows'];
            $total = $report['total'];
            $totalPages = $report['totalPages'];
            $page = $report['page'];
            $pageRows = $report['pageRows'];
            $rangeStart = $report['rangeStart'];
            $rangeEnd = $report['rangeEnd'];
            $totalPagu = $report['totalPagu'];
            $totalRealisasi = $report['totalRealisasi'];
            $totalSisa = $report['totalSisa'];
            $serapanPersen = $report['serapanPersen'];
            $sisaCount = $report['sisaCount'];
            $overCount = $report['overCount'];
            $chartData = $report['chartData'];

            $pageTitle = 'Analisa Sisa Anggaran';
            $activePage = 'analisa_sisa';
            $viewFile = __DIR__ . '/../../views/analisa_sisa_anggaran/index.php';

            include __DIR__ . '/../../views/layout.php';
        } catch (\Exception $e) {
            http_response_code(500);
            echo '<div class="container py-5"><div class="alert alert-danger">Gagal memuat analisa sisa anggaran: '
                . htmlspecialchars($e->getMessage()) . '</div></div>';
            error_log('AnalisaSisaAnggaran error: ' . $e->getMessage());
        }
    }

    public function exportExcel(): void
    {
        try {
            $report = $this->buildReport($_GET);
            (new AnalisaSisaAnggaranExportService())->download($report);
        } catch (\Throwable $e) {
            error_log('AnalisaSisaAnggaran export error: ' . $e->getMessage());
            http_response_code(500);
            echo '<div class="container py-5"><div class="alert alert-danger">Gagal membuat export Excel: '
                . htmlspecialchars($e->getMessage()) . '</div></div>';
        }
    }

    /**
     * Satu sumber perhitungan untuk tampilan halaman maupun export Excel.
     *
     * @param array<string,mixed> $params Biasanya $_GET.
     * @return array<string,mixed>
     */
    private function buildReport(array $params): array
    {
        $allPagus = $this->paguModel->getAll();

        // Daftar tahun tersedia (terbaru dulu)
        $tahunTersediaMap = [];
        foreach ($allPagus as $p) {
            $t = (int) ($p['tahun'] ?? 0);
            if ($t > 0) {
                $tahunTersediaMap[$t] = true;
            }
        }
        $tahunTersedia = array_keys($tahunTersediaMap);
        rsort($tahunTersedia);

        $tahunParam = isset($params['tahun']) && is_numeric($params['tahun']) ? (int) $params['tahun'] : null;
        if ($tahunParam !== null) {
            $tahun = $tahunParam;
        } elseif (in_array((int) date('Y'), $tahunTersedia, true)) {
            $tahun = (int) date('Y');
        } else {
            $tahun = $tahunTersedia[0] ?? (int) date('Y');
        }

        // Seksi
        $seksiList = $this->seksiModel->getAll();
        $seksiMap = [];
        foreach ($seksiList as $s) {
            $seksiMap[(int) $s['id']] = (string) $s['nama_seksi'];
        }

        // Filter
        $seksiFilter = isset($params['seksi']) && is_numeric($params['seksi']) ? (int) $params['seksi'] : 0;
        $minJuta = isset($params['min_juta']) && is_numeric($params['min_juta']) ? max(0, (float) $params['min_juta']) : 0.0;
        $minSisa = $minJuta * 1_000_000;
        $q = trim((string) ($params['q'] ?? ''));
        $sort = (string) ($params['sort'] ?? 'sisa_desc');
        $sortValid = ['sisa_desc', 'sisa_asc', 'pagu_desc', 'serapan_asc', 'serapan_desc', 'nama_asc'];
        if (!in_array($sort, $sortValid, true)) {
            $sort = 'sisa_desc';
        }

        // Realisasi per rekening untuk tahun terpilih (1 query).
        $realisasiMap = [];
        try {
            $realisasiMap = $this->paguModel->getRealisasiMapByTahun($tahun);
        } catch (\Throwable) {
            $realisasiMap = [];
        }

        // Bangun baris analisa
        $rows = [];
        foreach ($allPagus as $p) {
            if ((int) ($p['tahun'] ?? 0) !== $tahun) {
                continue;
            }

            $rid = (int) ($p['rekening_id'] ?? 0);
            $seksiId = (int) ($p['sub_kegiatan_seksi_id'] ?? 0);
            $pagu = (float) ($p['nilai_pagu'] ?? 0);
            $realisasi = (float) ($realisasiMap[$rid] ?? 0);
            $sisa = $pagu - $realisasi;

            if ($seksiFilter > 0 && $seksiId !== $seksiFilter) {
                continue;
            }
            if ($minSisa > 0 && $sisa < $minSisa) {
                continue;
            }
            if ($q !== '') {
                $haystack = strtolower(implode(' ', [
                    $p['kode_rekening'] ?? '',
                    $p['nama_rekening'] ?? '',
                    $p['kode_sub_kegiatan'] ?? '',
                    $p['nama_sub_kegiatan'] ?? '',
                    $p['kode_program'] ?? '',
                    $p['nama_program'] ?? '',
                    $p['kode_kegiatan'] ?? '',
                    $p['nama_kegiatan'] ?? '',
                ]));
                if (strpos($haystack, strtolower($q)) === false) {
                    continue;
                }
            }

            $rows[] = [
                'rekening_id' => $rid,
                'kode_rekening' => (string) ($p['kode_rekening'] ?? ''),
                'nama_rekening' => (string) ($p['nama_rekening'] ?? ''),
                'kode_sub_kegiatan' => (string) ($p['kode_sub_kegiatan'] ?? ''),
                'nama_sub_kegiatan' => (string) ($p['nama_sub_kegiatan'] ?? ''),
                'kode_kegiatan' => (string) ($p['kode_kegiatan'] ?? ''),
                'nama_kegiatan' => (string) ($p['nama_kegiatan'] ?? ''),
                'kode_program' => (string) ($p['kode_program'] ?? ''),
                'nama_program' => (string) ($p['nama_program'] ?? ''),
                'seksi_id' => $seksiId,
                'nama_seksi' => $seksiMap[$seksiId] ?? 'Tanpa Seksi',
                'pagu' => $pagu,
                'realisasi' => $realisasi,
                'sisa' => $sisa,
                'serapan' => $pagu > 0 ? ($realisasi / $pagu) * 100 : 0.0,
            ];
        }

        // Urutkan
        usort($rows, function (array $a, array $b) use ($sort): int {
            return match ($sort) {
                'sisa_asc' => $a['sisa'] <=> $b['sisa'],
                'pagu_desc' => $b['pagu'] <=> $a['pagu'],
                'serapan_asc' => $a['serapan'] <=> $b['serapan'],
                'serapan_desc' => $b['serapan'] <=> $a['serapan'],
                'nama_asc' => strcasecmp($a['nama_rekening'], $b['nama_rekening']),
                default => $b['sisa'] <=> $a['sisa'],
            };
        });

        // Ringkasan (dari seluruh hasil filter, bukan halaman aktif)
        $totalPagu = 0.0;
        $totalRealisasi = 0.0;
        $overCount = 0;
        $sisaCount = 0;
        $seksiAgg = [];
        $buckets = [
            'over' => ['label' => 'Over realisasi', 'count' => 0, 'total' => 0.0],
            '0-100' => ['label' => '0 – 100 jt', 'count' => 0, 'total' => 0.0],
            '100-500' => ['label' => '100 – 500 jt', 'count' => 0, 'total' => 0.0],
            '500-1000' => ['label' => '500 jt – 1 M', 'count' => 0, 'total' => 0.0],
            '1000+' => ['label' => '≥ 1 M', 'count' => 0, 'total' => 0.0],
        ];

        foreach ($rows as $r) {
            $totalPagu += $r['pagu'];
            $totalRealisasi += $r['realisasi'];
            if ($r['sisa'] < 0) {
                $overCount++;
                $buckets['over']['count']++;
                $buckets['over']['total'] += $r['sisa'];
                continue;
            }
            if ($r['sisa'] > 0) {
                $sisaCount++;
            }
            if ($r['sisa'] < 100_000_000) {
                $key = '0-100';
            } elseif ($r['sisa'] < 500_000_000) {
                $key = '100-500';
            } elseif ($r['sisa'] < 1_000_000_000) {
                $key = '500-1000';
            } else {
                $key = '1000+';
            }
            $buckets[$key]['count']++;
            $buckets[$key]['total'] += $r['sisa'];

            $sid = $r['seksi_id'];
            if (!isset($seksiAgg[$sid])) {
                $seksiAgg[$sid] = [
                    'id' => $sid,
                    'nama' => $r['nama_seksi'],
                    'sisa' => 0.0,
                    'count' => 0,
                ];
            }
            $seksiAgg[$sid]['sisa'] += $r['sisa'];
            $seksiAgg[$sid]['count']++;
        }

        $totalSisa = $totalPagu - $totalRealisasi;
        $serapanPersen = $totalPagu > 0 ? ($totalRealisasi / $totalPagu) * 100 : 0.0;

        uasort($seksiAgg, fn($a, $b) => $b['sisa'] <=> $a['sisa']);
        $seksiAgg = array_values(array_filter($seksiAgg, fn($s) => $s['sisa'] > 0));

        // Top 10 rekening berdasar sisa terbesar (untuk grafik)
        $topRows = $rows;
        usort($topRows, fn($a, $b) => $b['sisa'] <=> $a['sisa']);
        $topRows = array_slice(array_values(array_filter($topRows, fn($r) => $r['sisa'] > 0)), 0, 10);

        // Pagination
        $total = count($rows);
        $totalPages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = isset($params['page']) ? max(1, (int) $params['page']) : 1;
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * self::PER_PAGE;
        $pageRows = array_slice($rows, $offset, self::PER_PAGE);
        $rangeStart = $total > 0 ? $offset + 1 : 0;
        $rangeEnd = min($total, $offset + count($pageRows));

        $filters = [
            'tahun' => $tahun,
            'seksi' => $seksiFilter,
            'min_juta' => $minJuta,
            'q' => $q,
            'sort' => $sort,
        ];

        $chartData = [
            'topRekening' => array_map(fn($r) => [
                'label' => $r['kode_rekening'] . ' · ' . $r['nama_rekening'],
                'sisa' => $r['sisa'],
                'pagu' => $r['pagu'],
            ], $topRows),
            'perSeksi' => array_map(fn($s) => [
                'label' => $s['nama'],
                'sisa' => $s['sisa'],
                'count' => $s['count'],
            ], $seksiAgg),
            'buckets' => array_values($buckets),
            'rows' => $rows,
        ];

        return [
            'tahunTersedia' => $tahunTersedia,
            'tahun' => $tahun,
            'seksiList' => $seksiList,
            'seksiMap' => $seksiMap,
            'filters' => $filters,
            'rows' => $rows,
            'total' => $total,
            'totalPages' => $totalPages,
            'page' => $page,
            'pageRows' => $pageRows,
            'rangeStart' => $rangeStart,
            'rangeEnd' => $rangeEnd,
            'totalPagu' => $totalPagu,
            'totalRealisasi' => $totalRealisasi,
            'totalSisa' => $totalSisa,
            'serapanPersen' => $serapanPersen,
            'sisaCount' => $sisaCount,
            'overCount' => $overCount,
            'seksiAgg' => $seksiAgg,
            'topRows' => $topRows,
            'bucketList' => array_values($buckets),
            'chartData' => $chartData,
        ];
    }
}
