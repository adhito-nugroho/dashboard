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
            $tagRecap = $report['tagRecap'];
            $tagarList = $report['tagarList'];
            $hasUntagged = $report['hasUntagged'];

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
            error_log('AnalisaSisaAnggaran error: ' . $e->getMessage());
            $this->renderError(
                'Data analisa sisa anggaran gagal dimuat.',
                'Silakan coba lagi. Jika masalah berlanjut, hubungi administrator.'
            );
        }
    }

    public function exportExcel(): void
    {
        try {
            $report = $this->buildReport($_GET);
            (new AnalisaSisaAnggaranExportService())->download($report);
        } catch (\Throwable $e) {
            error_log('AnalisaSisaAnggaran export error: ' . $e->getMessage());
            $this->renderError(
                'Export Excel gagal dibuat.',
                'Silakan coba lagi atau reset filter, lalu unduh ulang.'
            );
        }
    }

    /**
     * Halaman error yang bisa dibaca pengguna (bukan area kosong / stack trace).
     * Detail teknis tetap dicatat ke error_log.
     */
    private function renderError(string $title, string $message): void
    {
        http_response_code(500);
        $retryUrl = htmlspecialchars(base_url('analisa-sisa-anggaran'), ENT_QUOTES);
        echo '<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>Gagal Memuat Data</title>'
            . '<style>body{font-family:Inter,system-ui,sans-serif;background:#eef1f8;color:#1e293b;margin:0;'
            . 'display:flex;align-items:center;justify-content:center;min-height:100vh;padding:24px}'
            . '.card{background:#fff;border:1px solid #e2e8f0;border-radius:16px;max-width:520px;width:100%;'
            . 'padding:24px;box-shadow:0 8px 24px rgba(15,23,42,.08)}'
            . 'h1{font-size:1.15rem;margin:0 0 8px;color:#b91c1c}p{font-size:.9rem;line-height:1.6;color:#475569}'
            . '.actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:16px}'
            . 'a{display:inline-flex;align-items:center;padding:9px 14px;border-radius:10px;font-size:.85rem;'
            . 'font-weight:700;text-decoration:none}'
            . '.primary{background:#4338ca;color:#fff}.secondary{background:#f1f5f9;color:#334155;border:1px solid #e2e8f0}'
            . '</style></head><body><div class="card">'
            . '<h1>' . htmlspecialchars($title) . '</h1>'
            . '<p>' . htmlspecialchars($message) . '</p>'
            . '<div class="actions"><a class="primary" href="' . $retryUrl . '">Muat ulang halaman</a>'
            . '<a class="secondary" href="' . $retryUrl . '?tahun=' . (int) date('Y') . '">Reset filter</a></div>'
            . '</div></body></html>';
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
        // '' = semua tagar, '__none__' = rekening tanpa tagar.
        $tagarFilter = trim((string) ($params['tagar'] ?? ''));
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
        $baseRows = [];        // filter lengkap (termasuk seksi & tagar) -> KPI, bucket, tabel, export
        $chartSeksiRows = [];  // tanpa filter seksi -> donut seluruh seksi
        $tagRecapRows = [];    // tanpa filter tagar -> rekap semua tagar (tetap ikut filter seksi)

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
                    $p['tagar'] ?? '',
                ]));
                if (strpos($haystack, strtolower($q)) === false) {
                    continue;
                }
            }

            $row = [
                'rekening_id' => $rid,
                'kode_rekening' => (string) ($p['kode_rekening'] ?? ''),
                'nama_rekening' => (string) ($p['nama_rekening'] ?? ''),
                'tagar' => trim((string) ($p['tagar'] ?? '')),
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

            // Rekap tagar mengabaikan filter tagar agar semua tag tetap terlihat.
            $tagRecapRows[] = $row;

            if ($tagarFilter !== '') {
                $match = $tagarFilter === '__none__'
                    ? $row['tagar'] === ''
                    : strcasecmp($row['tagar'], $tagarFilter) === 0;
                if (!$match) {
                    continue;
                }
            }
            $baseRows[] = $row;
        }

        // Rekap per tagar (dipakai halaman + sheet Excel).
        $tagAgg = [];
        $tagarSet = [];
        $hasUntagged = false;
        foreach ($tagRecapRows as $r) {
            $tag = trim((string) ($r['tagar'] ?? ''));
            if ($tag === '') {
                $hasUntagged = true;
                $key = '__none__';
            } else {
                $tagarSet[$tag] = true;
                $key = $tag;
            }
            if (!isset($tagAgg[$key])) {
                $tagAgg[$key] = [
                    'tagar' => $key,
                    'label' => $key === '__none__' ? 'Tanpa Tagar' : $key,
                    'count' => 0,
                    'pagu' => 0.0,
                    'realisasi' => 0.0,
                    'sisa' => 0.0,
                    'serapan' => 0.0,
                ];
            }
            $tagAgg[$key]['count']++;
            $tagAgg[$key]['pagu'] += $r['pagu'];
            $tagAgg[$key]['realisasi'] += $r['realisasi'];
            $tagAgg[$key]['sisa'] += $r['sisa'];
        }
        foreach ($tagAgg as &$t) {
            $t['serapan'] = $t['pagu'] > 0 ? ($t['realisasi'] / $t['pagu']) * 100 : 0.0;
        }
        unset($t);
        uasort($tagAgg, fn($a, $b) => $b['pagu'] <=> $a['pagu']);
        $tagRecap = array_values($tagAgg);
        // Pastikan tagar yang sedang difilter tetap muncul di dropdown walau
        // tidak ada rekeningnya pada filter seksi/pencarian saat ini.
        if ($tagarFilter !== '' && $tagarFilter !== '__none__') {
            $tagarSet[$tagarFilter] = true;
        } elseif ($tagarFilter === '__none__') {
            $hasUntagged = true;
        }
        $tagarList = array_keys($tagarSet);
        sort($tagarList, SORT_NATURAL | SORT_FLAG_CASE);

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

        // Chart batang: 10 baris teratas PERSIS dari urutan tabel yang aktif,
        // sehingga jumlah & urutan batang selalu sama dengan tabel (permintaan pengguna).
        $topRows = array_slice($displayRows, 0, 10);
        $topCandidates = count($displayRows);

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
            'tagar' => $tagarFilter,
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
            // Sertakan key bucket agar view bisa membedakan over-realisasi vs kelompok positif.
            'buckets' => array_map(
                static fn($key, $bucket) => ['key' => $key] + $bucket,
                array_keys($buckets),
                array_values($buckets)
            ),
            'rows' => $displayRows,
            // Satu sumber warna donut untuk PHP (legenda HTML) dan JS (chart).
            'palette' => ['#4338ca', '#059669', '#0284c7', '#d97706', '#db2777', '#7c3aed', '#0891b2', '#65a30d', '#ea580c', '#475569'],
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
            'tagRecap' => $tagRecap,
            'tagarList' => $tagarList,
            'hasUntagged' => $hasUntagged,
            'topRows' => $topRows,
            'bucketList' => array_values($buckets),
            'chartData' => $chartData,
        ];
    }
}
