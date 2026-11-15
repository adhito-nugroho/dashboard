<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Proyeksi rencana penyerapan otomatis per rekening.
 *
 * Kebutuhan rutin bulanan = rata-rata realisasi terverifikasi tiga bulan
 * sebelum bulan proyeksi (dibulatkan). Bulan berjalan hanya merencanakan sisa
 * kebutuhan setelah realisasi yang sudah tercatat; bulan berikutnya memakai
 * satu bulan penuh. Keduanya dibatasi sisa pagu setelah realisasi, rencana
 * manual, dan rencana TU, sehingga rencana tidak pernah melebihi pagu.
 *
 * Rencana TU tidak dihitung sebagai rutin; angkanya diambil dari pengajuan TU
 * berjalan sesuai bulan kebutuhan masing-masing pengajuan.
 */
class RencanaPenyerapanProjectionService
{
    private const LOOKBACK = 3;

    private const BULAN = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];

    /**
     * @param array<int,array<string,mixed>> $rekeningList daftar rekening ber-pagu
     * @param array<int,array<int,array{total:float,GU:float,TU:float,LS:float,UP:float}>> $realisasiBulanan
     * @param array<int,array<int,float>> $tuBulanan
     * @param array<int,array<int,array{total:float,GU:float,TU:float,LS:float,UP:float}>> $prevYearBulanan
     */
    public function build(
        int $tahun,
        int $bulan,
        array $rekeningList,
        array $realisasiBulanan,
        array $tuBulanan,
        array $prevYearBulanan = []
    ): array {
        $bulan = max(1, min(12, $bulan));
        $bulanDepan = $bulan < 12 ? $bulan + 1 : null;
        $lookback = $this->lookbackMonths($bulan);

        $rows = [];
        foreach ($rekeningList as $rek) {
            $rows[] = $this->buildRow(
                $tahun,
                $bulan,
                $bulanDepan,
                $lookback,
                $rek,
                $realisasiBulanan,
                $tuBulanan,
                $prevYearBulanan
            );
        }

        usort($rows, function (array $a, array $b): int {
            return strnatcmp($a['kode_program'], $b['kode_program'])
                ?: strnatcmp($a['kode_kegiatan'], $b['kode_kegiatan'])
                ?: strnatcmp($a['kode_sub_kegiatan'], $b['kode_sub_kegiatan'])
                ?: strnatcmp($a['kode_rekening'], $b['kode_rekening']);
        });

        $totals = [
            'count'          => count($rows),
            'pagu'           => 0.0,
            'realisasi_sd'   => 0.0,
            'rata_rata'      => 0.0,
            'rencana_manual' => 0.0,
            'tu_plan'        => 0.0,
            'plan_bulan'     => ['GU' => 0.0, 'TU' => 0.0, 'LS' => 0.0, 'total' => 0.0],
            'plan_depan'     => ['GU' => 0.0, 'TU' => 0.0, 'LS' => 0.0, 'total' => 0.0],
            'proyeksi'       => 0.0,
            'sisa_proyeksi'  => 0.0,
            'kekurangan'     => 0.0,
        ];

        foreach ($rows as $row) {
            foreach (['pagu', 'realisasi_sd', 'rata_rata', 'rencana_manual', 'tu_plan', 'proyeksi', 'sisa_proyeksi', 'kekurangan'] as $key) {
                $totals[$key] += (float) $row[$key];
            }
            foreach (['GU', 'TU', 'LS', 'total'] as $mek) {
                $totals['plan_bulan'][$mek] += (float) $row['plan_bulan'][$mek];
                $totals['plan_depan'][$mek] += (float) $row['plan_depan'][$mek];
            }
        }

        return [
            'tahun'             => $tahun,
            'bulan'             => $bulan,
            'bulan_depan'       => $bulanDepan,
            'label_bulan'       => self::BULAN[$bulan],
            'label_bulan_depan' => $bulanDepan !== null ? self::BULAN[$bulanDepan] : null,
            'lookback_labels'   => array_map(fn($m) => self::BULAN[$m['bulan']], $lookback),
            'rows'              => $rows,
            'totals'            => $totals,
        ];
    }

    /** @return array<int,array{prev:bool,bulan:int}> */
    private function lookbackMonths(int $bulan): array
    {
        $months = [];
        for ($i = self::LOOKBACK; $i >= 1; $i--) {
            $m = $bulan - $i;
            $months[] = [
                'prev'  => $m <= 0,
                'bulan' => $m <= 0 ? $m + 12 : $m,
            ];
        }
        return $months;
    }

    private function buildRow(
        int $tahun,
        int $bulan,
        ?int $bulanDepan,
        array $lookback,
        array $rek,
        array $realisasiBulanan,
        array $tuBulanan,
        array $prevYearBulanan
    ): array {
        $rekeningId = (int) $rek['id'];
        $pagu = (float) $rek['total_pagu'];
        $rencanaManual = (float) $rek['total_rencana'];
        $tuPlan = (float) $rek['total_tu'];
        $selesai = !empty($rek['selesai_id']);

        $realBulanan = $realisasiBulanan[$rekeningId] ?? [];
        $realisasiSd = 0.0;
        for ($m = 1; $m <= $bulan; $m++) {
            $realisasiSd += (float) ($realBulanan[$m]['total'] ?? 0);
        }
        $realisasiBulan = (float) ($realBulanan[$bulan]['total'] ?? 0);

        $lookbackTotals = [];
        $lookbackGu = 0.0;
        $lookbackLs = 0.0;
        foreach ($lookback as $src) {
            $map = $src['prev'] ? $prevYearBulanan : $realisasiBulanan;
            $rowBulan = $map[$rekeningId][$src['bulan']] ?? [];
            $lookbackTotals[] = (float) ($rowBulan['total'] ?? 0);
            $lookbackGu += (float) ($rowBulan['GU'] ?? 0);
            $lookbackLs += (float) ($rowBulan['LS'] ?? 0);
        }
        $rataRata = (float) round(array_sum($lookbackTotals) / self::LOOKBACK);

        $sisaAwal = $pagu - $realisasiSd - $rencanaManual - $tuPlan;
        $tersedia = max(0.0, $sisaAwal);

        $needBulan = max(0.0, $rataRata - $realisasiBulan);
        $needDepan = $bulanDepan !== null ? $rataRata : 0.0;

        if ($selesai) {
            $needBulan = 0.0;
            $needDepan = 0.0;
            $planBulanRutin = 0.0;
            $planDepanRutin = 0.0;
            $kekurangan = 0.0;
        } else {
            $planBulanRutin = min($needBulan, $tersedia);
            $planDepanRutin = min($needDepan, max(0.0, $tersedia - $planBulanRutin));
            $kekurangan = max(0.0, $needBulan + $needDepan - $tersedia);
        }

        [$planBulanGu, $planBulanLs] = $this->splitMechanism($planBulanRutin, $lookbackGu, $lookbackLs);
        [$planDepanGu, $planDepanLs] = $this->splitMechanism($planDepanRutin, $lookbackGu, $lookbackLs);

        $tuBulan = (float) ($tuBulanan[$rekeningId][$bulan] ?? 0);
        $tuDepan = $bulanDepan !== null ? (float) ($tuBulanan[$rekeningId][$bulanDepan] ?? 0) : 0.0;

        $proyeksi = $realisasiSd + $rencanaManual + $tuPlan + $planBulanRutin + $planDepanRutin;
        $sisaProyeksi = $pagu - $proyeksi;

        $keterangan = [];
        if ($selesai) {
            $keterangan[] = 'Sudah ditandai selesai';
        }
        if ($rataRata <= 0) {
            $keterangan[] = 'Belum ada pola rutin 3 bulan';
        }
        if ($rencanaManual > 0.5) {
            $keterangan[] = 'Rencana manual Rp ' . number_format($rencanaManual, 0, ',', '.');
        }
        $tuBulanLain = $tuPlan - $tuBulan - $tuDepan;
        if ($tuBulanLain > 0.5) {
            $keterangan[] = 'TU bulan lain Rp ' . number_format($tuBulanLain, 0, ',', '.');
        }
        if ($kekurangan > 0.5) {
            $keterangan[] = 'Potensi kekurangan Rp ' . number_format($kekurangan, 0, ',', '.');
        } elseif ($sisaAwal < -0.5) {
            $keterangan[] = 'Rencana + realisasi melebihi pagu';
        }
        if ($bulanDepan === null) {
            $keterangan[] = 'Desember adalah bulan terakhir';
        }
        if ($rataRata > 0 && $lookbackGu + $lookbackLs <= 0 && $planBulanRutin + $planDepanRutin > 0) {
            $keterangan[] = 'Mekanisme GU (belum ada pola GU/LS)';
        }
        if (empty($keterangan)) {
            $keterangan[] = 'Cukup';
        }

        return [
            'rekening_id'       => $rekeningId,
            'kode_rekening'     => (string) $rek['kode_rekening'],
            'nama_rekening'     => (string) $rek['nama_rekening'],
            'kode_program'      => (string) ($rek['kode_program'] ?? ''),
            'nama_program'      => (string) ($rek['nama_program'] ?? ''),
            'kode_kegiatan'     => (string) ($rek['kode_kegiatan'] ?? ''),
            'nama_kegiatan'     => (string) ($rek['nama_kegiatan'] ?? ''),
            'kode_sub_kegiatan' => (string) ($rek['kode_sub_kegiatan'] ?? ''),
            'nama_sub_kegiatan' => (string) ($rek['nama_sub_kegiatan'] ?? ''),
            'nama_seksi'        => (string) ($rek['nama_seksi'] ?? ''),
            'selesai'           => $selesai,
            'pagu'              => $pagu,
            'realisasi_sd'      => $realisasiSd,
            'realisasi_bulan'   => $realisasiBulan,
            'rata_rata'         => $rataRata,
            'rencana_manual'    => $rencanaManual,
            'tu_plan'           => $tuPlan,
            'need_bulan'        => $needBulan,
            'need_depan'        => $needDepan,
            'sisa_awal'         => $sisaAwal,
            'plan_bulan'        => [
                'GU'    => $planBulanGu,
                'TU'    => $tuBulan,
                'LS'    => $planBulanLs,
                'total' => $planBulanGu + $tuBulan + $planBulanLs,
            ],
            'plan_depan'        => [
                'GU'    => $planDepanGu,
                'TU'    => $tuDepan,
                'LS'    => $planDepanLs,
                'total' => $planDepanGu + $tuDepan + $planDepanLs,
            ],
            'proyeksi'          => $proyeksi,
            'pct_proyeksi'      => $pagu > 0 ? $proyeksi / $pagu : 0.0,
            'sisa_proyeksi'     => $sisaProyeksi,
            'kekurangan'        => $kekurangan,
            'keterangan'        => implode('; ', $keterangan),
        ];
    }

    /**
     * Bagi rencana rutin mengikuti proporsi realisasi GU/LS tiga bulan terakhir.
     *
     * @return array{0:float,1:float} [GU, LS]
     */
    private function splitMechanism(float $amount, float $gu, float $ls): array
    {
        if ($amount <= 0) {
            return [0.0, 0.0];
        }
        $basis = $gu + $ls;
        if ($basis <= 0) {
            return [$amount, 0.0];
        }
        $guPart = (float) round($amount * ($gu / $basis));
        $guPart = max(0.0, min($amount, $guPart));

        return [$guPart, $amount - $guPart];
    }
}
