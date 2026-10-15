<?php
// Footer halaman (dipindah dari posisi semula di antara blok script).
?>
<footer style="text-align:center;padding:1.5rem 1rem;color:var(--gray-400);font-size:var(--fs-sm);border-top:1px solid var(--gray-100);">
    <p style="margin:0;">&copy; <?= date('Y') ?> Sistem Informasi Monitoring Anggaran Cabang Dinas Kehutanan Wilayah Bojonegoro</p>
</footer>
<?php
// Boot data untuk public/js/dashboard.js — pengganti injeksi PHP inline.
// Dieksekusi dalam scope view yang sama sehingga semua variabel tersedia.
$bootBulanLong = [1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',
                  7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'];

// Baris export Deviasi: [skid, subKegiatan, rekening, kodeRek, bulan, rak, realisasi, selisih, status, risiko].
// Risiko dihitung PER sub kegiatan (sama persis rumusnya dengan section Deviasi).
$bootDeviasiRows = [];
if (!empty($deviationDetails)) {
    foreach ($deviationDetails as $skid => $sk) {
        $skOver = 0; $skUnder = 0; $skTotalRak = 0; $skTotalAbs = 0;
        foreach ($sk['rekening'] as $r) {
            foreach ($r['deviations'] as $d) {
                $d['type'] === 'over' ? $skOver++ : $skUnder++;
                $skTotalRak += $d['rak'];
                $skTotalAbs += abs($d['selisih']);
            }
        }
        $skPct = $skTotalRak > 0 ? ($skTotalAbs / $skTotalRak) * 100 : 0;
        $skRisk = $skPct > 50 ? 'Kritis' : ($skPct >= 20 ? 'Sedang' : 'Rendah');
        foreach ($sk['rekening'] as $rek) {
            foreach ($rek['deviations'] as $dev) {
                $bootDeviasiRows[] = [
                    $skid,
                    $sk['nama'],
                    $rek['nama'],
                    $rek['kode'],
                    $bootBulanLong[$dev['bulan']] ?? $dev['bulan'],
                    (float) $dev['rak'],
                    (float) $dev['realisasi'],
                    (float) $dev['selisih'],
                    $dev['type'] === 'over' ? 'Over RAK' : 'Under RAK',
                    $skRisk,
                ];
            }
        }
    }
}

// Baris export Breakdown: [nama, pagu, realisasi, sisa, %].
$bootBreakdownRows = [];
if (!empty($breakdownData)) {
    foreach ($breakdownData as $name => $d) {
        $bootBreakdownRows[] = [
            $name,
            (float) $d['pagu'],
            (float) $d['realisasi'],
            (float) ($d['pagu'] - $d['realisasi']),
            $d['pagu'] > 0 ? round(($d['realisasi'] / $d['pagu']) * 100, 2) : 0,
        ];
    }
}

$bootAlertTypes = [];
foreach (($monthlyData['alerts'] ?? []) as $a) {
    $bootAlertTypes[$a['bulan']] = $a['type'];
}

$boot = [
    'urls' => [
        'laporan'  => base_url('export/laporan'),
        'rak'      => base_url('rak/export'),
        'bulanan'  => base_url('export/serapan-bulanan'),
        'triwulan' => base_url('export/serapan-triwulan'),
        'semester' => base_url('export/sisa-semester'),
    ],
    'resetUrl' => base_url() . '?tahun=' . $stats['tahun'],
    'tahun' => (int) $stats['tahun'],
    'bulanBerjalan' => $bulanBerjalanDetail ?? 12,
    'isTahunIni' => $stats['tahun'] == (int) date('Y'),
    'monthly' => [
        'rak' => array_values($monthlyData['rak']),
        'realisasi' => array_values($monthlyData['realisasi']),
        'labels' => array_values($bulanNames),
        'alertBulan' => array_column($monthlyData['alerts'] ?? [], 'bulan'),
        'alertTypes' => $bootAlertTypes,
    ],
    'breakdown' => $breakdownData,
    'breakdownRows' => $bootBreakdownRows,
    'deviasiRows' => $bootDeviasiRows,
];
?>
<script>
window.DashboardBoot = <?= json_encode($boot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
</script>
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="<?= base_url('js/dashboard.js') ?>"></script>
