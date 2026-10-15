<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Transaksi;

/**
 * Satu-satunya sumber perhitungan serapan/realisasi. Dipakai bersama oleh
 * DashboardController (tampilan) dan ExcelController (export) agar angka
 * di layar dan di file Excel selalu identik.
 *
 * Method menerima array $pagus (hasil Pagu::getAll()) sehingga murni fungsi
 * dari inputnya dan mudah di-unit-test. RAK & transaksi dimuat sendiri via
 * PDO yang di-inject (1 query per tahun, di-cache).
 */
class SerapanReportService
{
    private array $rakByYearCache = [];
    private array $transaksiByYearCache = [];

    public function __construct(private \PDO $db)
    {
    }

    // ──────────────────────────────────────────────────────────
    // ABSORPTION (bulanan) — shape sama persis dengan yang dipakai view dashboard
    // ──────────────────────────────────────────────────────────

    /**
     * Serapan bulanan per Sub Kegiatan -> Rekening.
     *
     * @param array<int, array<string, mixed>> $pagus
     * @param array{seksi_id?: ?int, program_id?: ?int, kegiatan_id?: ?int, sub_kegiatan_id?: ?int} $filters
     * @return array{sub_kegiatan: array, totals: array}
     */
    public function absorption(array $pagus, int $tahun, array $filters): array
    {
        $subKegiatanMap = [];
        $totals = [
            'pagu' => 0,
            'realisasi' => 0,
            'rekening_count' => 0,
            'months' => array_fill(1, 12, 0),
        ];

        foreach ($pagus as $pagu) {
            if (($pagu['tahun'] ?? null) != $tahun || !$this->matchesFilters($pagu, $filters)) {
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
            foreach ($this->getTransaksiByRekeningAndYear($rid, $tahun) as $transaksi) {
                $bulan = Transaksi::bulanEfektifRealisasi($transaksi);
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

        $this->sortByKode($subKegiatanMap);

        return [
            'sub_kegiatan' => $subKegiatanMap,
            'totals' => $totals,
        ];
    }

    /**
     * Serapan yang sudah diagregasi per periode (triwulan/dll).
     *
     * @param array<int, array{label: string, months: int[]}> $periods Periode 1-indexed.
     * @return array{0: array, 1: array} [$subKegiatanMap, $totals] dengan key 'periods'.
     */
    public function absorptionPerPeriod(array $pagus, int $tahun, array $filters, array $periods): array
    {
        $nPeriods = count($periods);
        $monthToPeriod = [];
        foreach ($periods as $pIdx => $pDef) {
            foreach ($pDef['months'] as $m) {
                $monthToPeriod[$m] = $pIdx;
            }
        }

        $data = $this->absorption($pagus, $tahun, $filters);
        $bucketize = function (array $months) use ($monthToPeriod, $nPeriods): array {
            $out = array_fill(1, $nPeriods, 0);
            foreach ($months as $m => $val) {
                if (isset($monthToPeriod[$m])) {
                    $out[$monthToPeriod[$m]] += $val;
                }
            }
            return $out;
        };

        $map = [];
        foreach ($data['sub_kegiatan'] as $skid => $sk) {
            $rekening = [];
            foreach ($sk['rekening'] as $rid => $rek) {
                $rekening[$rid] = [
                    'kode' => $rek['kode'],
                    'nama' => $rek['nama'],
                    'pagu' => $rek['pagu'],
                    'realisasi' => $rek['realisasi'],
                    'periods' => $bucketize($rek['months']),
                ];
            }
            $map[$skid] = [
                'kode' => $sk['kode'],
                'nama' => $sk['nama'],
                'pagu' => $sk['pagu'],
                'realisasi' => $sk['realisasi'],
                'periods' => $bucketize($sk['months']),
                'rekening' => $rekening,
            ];
        }

        return [
            $map,
            [
                'pagu' => $data['totals']['pagu'],
                'realisasi' => $data['totals']['realisasi'],
                'periods' => $bucketize($data['totals']['months']),
            ],
        ];
    }

    // ──────────────────────────────────────────────────────────
    // SEMESTER (sisa dana) — superset shape untuk view dashboard & export Excel
    // ──────────────────────────────────────────────────────────

    /**
     * Sisa dana per semester per rekening. Sisa = RAK semester − Realisasi
     * semester; sisa positif S1 di-carry ke S2.
     *
     * @param array<int, array<string, mixed>> $pagus
     * @return array{sub_kegiatan: array, totals: array}
     */
    public function semester(array $pagus, int $tahun, array $filters): array
    {
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
            if (($pagu['tahun'] ?? null) != $tahun || !$this->matchesFilters($pagu, $filters)) {
                continue;
            }

            $skid   = (int) $pagu['sub_kegiatan_id'];
            $rid    = (int) $pagu['rekening_id'];
            $skNama = $pagu['nama_sub_kegiatan'] ?? 'Unknown';
            $skKode = $pagu['kode_sub_kegiatan'] ?? '';
            $rNama  = $pagu['nama_rekening'] ?? 'Unknown';
            $rKode  = $pagu['kode_rekening'] ?? '';

            $rakS1 = 0;
            $rakS2 = 0;
            foreach ($this->getRakByRekeningAndYear($rid, $tahun) as $r) {
                $b = (int) $r['bulan'];
                $val = (float) $r['nilai_rak'];
                if ($b >= 1 && $b <= 6) {
                    $rakS1 += $val;
                } elseif ($b >= 7 && $b <= 12) {
                    $rakS2 += $val;
                }
            }

            $realS1 = 0;
            $realS2 = 0;
            foreach ($this->getTransaksiByRekeningAndYear($rid, $tahun) as $t) {
                $b = Transaksi::bulanEfektifRealisasi($t);
                $val = (float) $t['nilai'];
                if ($b >= 1 && $b <= 6) {
                    $realS1 += $val;
                } elseif ($b >= 7 && $b <= 12) {
                    $realS2 += $val;
                }
            }

            $sisaS1 = $rakS1 - $realS1;
            $sisaS2 = $rakS2 - $realS2;

            // Carry-over: sisa positif S1 menambah kuota yang tersedia di S2.
            $carryOver = max(0, $sisaS1);
            $sisaS2Efektif = $rakS2 + $carryOver - $realS2;

            // Skip rekening tanpa RAK sama sekali (tidak relevan).
            if ($rakS1 == 0 && $rakS2 == 0) {
                continue;
            }

            if (!isset($subKegiatanMap[$skid])) {
                $subKegiatanMap[$skid] = [
                    'kode' => $skKode,
                    'nama' => $skNama,
                    'rekening' => [],
                    'rak_s1' => 0, 'real_s1' => 0, 'sisa_s1' => 0,
                    'rak_s2' => 0, 'real_s2' => 0, 'sisa_s2' => 0,
                    'carry_over' => 0, 'sisa_total' => 0,
                ];
            }

            $subKegiatanMap[$skid]['rekening'][$rid] = [
                'kode' => $rKode,
                'nama' => $rNama,
                'pagu' => (float) $pagu['nilai_pagu'],
                'rak_s1' => $rakS1,
                'real_s1' => $realS1,
                'sisa_s1' => $sisaS1,
                'rak_s2' => $rakS2,
                'real_s2' => $realS2,
                'sisa_s2' => $sisaS2Efektif,   // sudah termasuk carry-over dari S1
                'carry_over' => $carryOver,      // sisa S1 yang di-carry ke S2
                'sisa_s2_raw' => $sisaS2,        // sisa S2 murni tanpa carry-over
                'sisa_total' => $sisaS1 + $sisaS2, // arithmetic total
            ];

            $subKegiatanMap[$skid]['rak_s1'] += $rakS1;
            $subKegiatanMap[$skid]['real_s1'] += $realS1;
            $subKegiatanMap[$skid]['sisa_s1'] += $sisaS1;
            $subKegiatanMap[$skid]['rak_s2'] += $rakS2;
            $subKegiatanMap[$skid]['real_s2'] += $realS2;
            $subKegiatanMap[$skid]['sisa_s2'] += $sisaS2Efektif;
            $subKegiatanMap[$skid]['carry_over'] = ($subKegiatanMap[$skid]['carry_over'] ?? 0) + $carryOver;
            $subKegiatanMap[$skid]['sisa_total'] += $sisaS1 + $sisaS2;

            $totals['rak_s1'] += $rakS1;
            $totals['real_s1'] += $realS1;
            $totals['sisa_s1'] += $sisaS1;
            $totals['rak_s2'] += $rakS2;
            $totals['real_s2'] += $realS2;
            $totals['sisa_s2'] += $sisaS2Efektif;
            $totals['carry_over'] = ($totals['carry_over'] ?? 0) + $carryOver;
            $totals['total_rekening']++;
            if ($sisaS1 > 0) {
                $totals['rekening_dengan_sisa_s1']++;
            }
            if ($sisaS2Efektif > 0) {
                $totals['rekening_dengan_sisa_s2']++;
            }
        }

        $this->sortByKode($subKegiatanMap);

        return [
            'sub_kegiatan' => $subKegiatanMap,
            'totals' => $totals,
        ];
    }

    // ──────────────────────────────────────────────────────────
    // PRIVATE HELPERS
    // ──────────────────────────────────────────────────────────

    private function matchesFilters(array $pagu, array $filters): bool
    {
        if (!empty($filters['seksi_id'])) {
            if (!isset($pagu['sub_kegiatan_seksi_id']) || $pagu['sub_kegiatan_seksi_id'] != $filters['seksi_id']) {
                return false;
            }
        }
        if (!empty($filters['program_id'])) {
            if (!isset($pagu['program_id']) || $pagu['program_id'] != $filters['program_id']) {
                return false;
            }
        }
        if (!empty($filters['kegiatan_id'])) {
            if (!isset($pagu['kegiatan_id']) || $pagu['kegiatan_id'] != $filters['kegiatan_id']) {
                return false;
            }
        }
        if (!empty($filters['sub_kegiatan_id'])) {
            if (!isset($pagu['sub_kegiatan_id']) || $pagu['sub_kegiatan_id'] != $filters['sub_kegiatan_id']) {
                return false;
            }
        }
        return true;
    }

    private function sortByKode(array &$subKegiatanMap): void
    {
        uasort($subKegiatanMap, fn($a, $b) => strcmp($a['kode'], $b['kode']));
        foreach ($subKegiatanMap as &$sk) {
            uasort($sk['rekening'], fn($a, $b) => strcmp($a['kode'], $b['kode']));
        }
        unset($sk);
    }

    private function loadRakByYear(int $tahun): void
    {
        if (isset($this->rakByYearCache[$tahun])) {
            return;
        }
        $stmt = $this->db->prepare('SELECT * FROM rak WHERE tahun = :tahun ORDER BY bulan ASC');
        $stmt->execute([':tahun' => $tahun]);
        $byRekening = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $r) {
            $byRekening[(int) $r['rekening_id']][] = $r;
        }
        $this->rakByYearCache[$tahun] = $byRekening;
    }

    private function getRakByRekeningAndYear(int $rekeningId, int $tahun): array
    {
        $this->loadRakByYear($tahun);
        return $this->rakByYearCache[$tahun][$rekeningId] ?? [];
    }

    private function loadTransaksiByYear(int $tahun): void
    {
        if (isset($this->transaksiByYearCache[$tahun])) {
            return;
        }
        try {
            $stmt = $this->db->prepare('
                SELECT * FROM transaksi
                WHERE YEAR(COALESCE(tanggal_lunas_dibayar, DATE(diverifikasi_at), tanggal)) = :tahun
                AND status = \'diverifikasi\'
                ORDER BY COALESCE(tanggal_lunas_dibayar, DATE(diverifikasi_at), tanggal) DESC
            ');
            $stmt->execute([':tahun' => $tahun]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            // Fallback jika kolom status belum ada (lingkungan lokal belum migrate)
            $stmt = $this->db->prepare('
                SELECT * FROM transaksi
                WHERE YEAR(tanggal) = :tahun
                ORDER BY tanggal DESC
            ');
            $stmt->execute([':tahun' => $tahun]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        }
        $byRekening = [];
        foreach ($rows as $t) {
            $byRekening[(int) $t['rekening_id']][] = $t;
        }
        $this->transaksiByYearCache[$tahun] = $byRekening;
    }

    private function getTransaksiByRekeningAndYear(int $rekeningId, int $tahun): array
    {
        $this->loadTransaksiByYear($tahun);
        return $this->transaksiByYearCache[$tahun][$rekeningId] ?? [];
    }
}
