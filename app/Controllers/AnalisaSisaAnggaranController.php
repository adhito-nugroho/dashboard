<?php

namespace App\Controllers;

use App\Models\Pagu;
use App\Models\Seksi;
use App\Services\AnalisaSisaAnggaranExportService;

/**
 * Analisa Sisa Anggaran
 *
 * Rumus tetap: sisa = pagu - realisasi terverifikasi.
 * Perhitungan ini dipakai bersama oleh tampilan, CSV (data di view), dan Excel.
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
            $baseCount = $report['baseCount'];
            $hiddenZeroCount = $report['hiddenZeroCount'];
            $showZero = $report['showZero'];
            $chartData = $report['chartData'];
            $seksiMeta = $report['seksiMeta'];

            $pageTitle = 'Analisa Sisa Anggaran';
            $activePage = 'analisa_sisa';
            // Topbar: tampilkan breadcrumb, bukan judul yang sama dengan heading halaman.
            $pageBreadcrumb = [
                ['label' => 'Monitoring Anggaran', 'url' => base_url()],
                ['label' => 'Analisa Sisa Anggaran', 'url' => null],
            ];
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
     * Satu sumber perhitungan untuk halaman, CSV (data view), dan Excel.
     *
     * Catatan desain:
     * - KPI dihitung dari $baseRows (hasil filter tahun/seksi/ambang/cari, termasuk
     *   rekening sisa <= 0) agar angka KPI tidak berubah oleh opsi tampilan.
     * - $rows yang ditampilkan/ diexport adalah $displayRows: default tanpa sisa <= 0.
     * - Donut seksi memakai $chartSeksiRows (mengabaikan filter seksi) supaya tetap
     *   memberi gambaran seluruh seksi; filter seksi hanya memengaruhi tabel.
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
        $showZero = !empty($params['show_zero']) && (string) $params['show_zero'] !== '0';

        // Realisasi per rekening untuk tahun terpilih (1 query).
        $realisasiMap = [];
        try {
            $realisasiMap = $this->paguModel->getRealisasiMapByTahun($tahun);
        } catch (\Throwable) {
            $realisasiMap = [];
        }

        // Bangun baris
        $baseRows = [];        // filter lengkap (termasuk seksi) -> KPI, bucket, tabel, export
        $chartSeksiRows = [];  // tanpa filter seksi -> donut seluruh seksi

        foreach ($allPagus as $p) {
            if ((int) ($p['tahun'] ?? 0) !== $tahun) {
                continue;
            }

            $rid = (int) ($p['rekening_id'] ?? 0);
            $seksiId = (int) ($p['sub_kegiatan_seksi_id'] ?? 0);
            $pagu = (float) ($p['nilai_pagu'] ?? 0);
            $realisasi = (float) ($realisasiMap[$rid] ?? 0);
            $sisa = $pagu - $realisasi;

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

            $row = [
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

            // Donut: seluruh seksi (filter seksi diabaikan).
            $chartSeksiRows[] = $row;

            if ($seksiFilter > 0 && $seksiId !== $seksiFilter) {
                continue;
            }
            $baseRows[] = $row;
        }

        // Urutkan tabel sesuai pilihan (dipakai tabel + export + CSV).
        usort($baseRows, function (array $a, array $b) use ($sort): int {
            return match ($sort) {
                'sisa_asc' => $a['sisa'] <=> $b['sisa'],
                'pagu_desc' => $b['pagu'] <=> $a['pagu'],
                'serapan_asc' => $a['serapan'] <=> $b['serapan'],
                'serapan_desc' => $b['serapan'] <=> $a['serapan'],
                'nama_asc' => strcasecmp($a['nama_rekening'], $b['nama_rekening']),
                default => $b['sisa'] <=> $a['sisa'],
            };
        });

        // Ringkasan KPI & distribusi — dari baseRows (termasuk sisa 0), angka tetap.
        $totalPagu = 0.0;
        $totalRealisasi = 0.0;
        $overCount = 0;
        $sisaCount = 0;
        $hiddenZeroCount = 0;
        $buckets = [
            'over' => ['label' => 'Over realisasi', 'count' => 0, 'total' => 0.0],
            '0-100' => ['label' => '0 – 100 jt', 'count' => 0, 'total' => 0.0],
            '100-500' => ['label' => '100 – 500 jt', 'count' => 0, 'total' => 0.0],
            '500-1000' => ['label' => '500 jt – 1 M', 'count' => 0, 'total' => 0.0],
            '1000+' => ['label' => '≥ 1 M', 'count' => 0, 'total' => 0.0],
        ];

        foreach ($baseRows as $r) {
            $totalPagu += $r['pagu'];
            $totalRealisasi += $r['realisasi'];

            if ($r['sisa'] < 0) {
                $overCount++;
            } elseif ($r['sisa'] === 0.0) {
                if (!$showZero) {
                    $hiddenZeroCount++;
                }
            } else {
                $sisaCount++;
            }
        }

        $totalSisa = $totalPagu - $totalRealisasi;
        $serapanPersen = $totalPagu > 0 ? ($totalRealisasi / $totalPagu) * 100 : 0.0;

        // Baris yang ditampilkan: default sembunyikan rekening tanpa sisa (sisa = 0).
        // Rekening over realisasi (sisa negatif) tetap tampil karena perlu ditindaklanjuti.
        $displayRows = $showZero
            ? $baseRows
            : array_values(array_filter($baseRows, fn($r) => $r['sisa'] != 0.0));

        // Distribusi dihitung dari baris yang tampil agar konsisten dengan tabel.
        foreach ($displayRows as $r) {
            if ($r['sisa'] < 0) {
                $buckets['over']['count']++;
                $buckets['over']['total'] += $r['sisa'];
                continue;
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
        }

        // Top chart: dari displayRows positif, selalu urut sisa terbesar.
        $positiveRows = array_values(array_filter($displayRows, fn($r) => $r['sisa'] > 0));
        usort($positiveRows, fn($a, $b) => $b['sisa'] <=> $a['sisa']);
        $topCandidates = count($positiveRows);
        $topRows = array_slice($positiveRows, 0, 10);

        // Donut: agregasi seluruh seksi (tanpa filter seksi), sisa positif saja.
        $seksiAgg = [];
        foreach ($chartSeksiRows as $r) {
            if ($r['sisa'] <= 0) {
                continue;
            }
            $sid = $r['seksi_id'];
            if (!isset($seksiAgg[$sid])) {
                $seksiAgg[$sid] = [
                    'id' => $sid,
                    'nama' => $r['nama_seksi'],
                    'sisa' => 0.0,
                    'pagu' => 0.0,
                    'count' => 0,
                ];
            }
            $seksiAgg[$sid]['sisa'] += $r['sisa'];
            $seksiAgg[$sid]['pagu'] += $r['pagu'];
            $seksiAgg[$sid]['count']++;
        }
        uasort($seksiAgg, fn($a, $b) => $b['sisa'] <=> $a['sisa']);
        $seksiAgg = array_values($seksiAgg);

        $filteredSeksiCountMap = [];
        foreach ($baseRows as $r) {
            if ($r['sisa'] <= 0) {
                continue;
            }
            $filteredSeksiCountMap[$r['seksi_id']] = true;
        }
        $filteredSeksiCount = count($filteredSeksiCountMap);

        $singleSeksi = null;
        if (count($seksiAgg) === 1) {
            $only = $seksiAgg[0];
            $singleSeksi = [
                'nama' => $only['nama'],
                'sisa' => $only['sisa'],
                'pagu' => $only['pagu'],
                'persen' => $only['pagu'] > 0 ? ($only['sisa'] / $only['pagu']) * 100 : 0.0,
            ];
        }

        $seksiMeta = [
            'count' => count($seksiAgg),
            'filteredCount' => $filteredSeksiCount,
            'filterActive' => $seksiFilter > 0,
            'single' => $singleSeksi,
        ];

        // Pagination
        $total = count($displayRows);
        $totalPages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = isset($params['page']) ? max(1, (int) $params['page']) : 1;
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * self::PER_PAGE;
        $pageRows = array_slice($displayRows, $offset, self::PER_PAGE);
        $rangeStart = $total > 0 ? $offset + 1 : 0;
        $rangeEnd = min($total, $offset + count($pageRows));

        $filters = [
            'tahun' => $tahun,
            'seksi' => $seksiFilter,
            'min_juta' => $minJuta,
            'q' => $q,
            'sort' => $sort,
            'show_zero' => $showZero ? 1 : 0,
        ];

        $chartData = [
            'topRekening' => array_map(fn($r) => [
                'kode' => $r['kode_rekening'],
                'nama' => $r['nama_rekening'],
                'seksi' => $r['nama_seksi'],
                'sisa' => $r['sisa'],
                'pagu' => $r['pagu'],
            ], $topRows),
            'topShown' => count($topRows),
            'topCandidates' => $topCandidates,
            'perSeksi' => array_map(fn($s) => [
                'label' => $s['nama'],
                'sisa' => $s['sisa'],
                'pagu' => $s['pagu'],
                'count' => $s['count'],
            ], $seksiAgg),
            'seksiMeta' => $seksiMeta,
            'buckets' => array_values($buckets),
            'rows' => $displayRows,
        ];

        return [
            'tahunTersedia' => $tahunTersedia,
            'tahun' => $tahun,
            'seksiList' => $seksiList,
            'seksiMap' => $seksiMap,
            'filters' => $filters,
            'rows' => $displayRows,
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
            'baseCount' => count($baseRows),
            'hiddenZeroCount' => $hiddenZeroCount,
            'showZero' => $showZero,
            'seksiMeta' => $seksiMeta,
            'seksiAgg' => $seksiAgg,
            'topRows' => $topRows,
            'bucketList' => array_values($buckets),
            'chartData' => $chartData,
        ];
    }
}
