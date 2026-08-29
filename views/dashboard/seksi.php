<?php
$percentage = (float) ($stats['percentage'] ?? 0);
$ringPercent = max(0, min(100, $percentage));
$ringDash = number_format($ringPercent, 2, '.', '');
$chartYear = (int) ($stats['tahun'] ?? date('Y'));
$currentYear = (int) date('Y');
$currentMonth = (int) date('n');
?>
<style>
.dashboard-shell { max-width: 1440px; margin: 0 auto; }
.dash-eyebrow { font-size: .72rem; font-weight: 800; letter-spacing: .09em; text-transform: uppercase; color: #64748b; }
.hero-card {
    border: 1px solid #e2e8f0; border-radius: 24px; background: #fff;
    box-shadow: 0 18px 45px rgba(15,23,42,.08); overflow: hidden;
}
.hero-accent { background: linear-gradient(135deg, #eef2ff 0%, #ffffff 54%, #f8fafc 100%); }
.hero-ring-wrap { width: 190px; height: 190px; position: relative; }
.hero-ring-text { position: absolute; inset: 0; display: grid; place-content: center; text-align: center; }
.hero-ring-value { font-size: 2.65rem; line-height: 1; font-weight: 900; color: #312e81; letter-spacing: -.04em; }
.support-card { border: 1px solid #e2e8f0; border-radius: 18px; background: #fff; padding: 1rem; height: 100%; }
.support-value { font-size: 1.1rem; font-weight: 850; color: #0f172a; letter-spacing: -.025em; }
.chart-card { border: 1px solid #e2e8f0; border-radius: 24px; background: #fff; box-shadow: 0 10px 30px rgba(15,23,42,.06); }
.chart-wrapper { position: relative; height: 350px; }
.legend-dot { width: 10px; height: 10px; border-radius: 50%; display: inline-block; }
@media (max-width: 768px) { .hero-ring-wrap { width: 150px; height: 150px; } .hero-ring-value { font-size: 2.1rem; } .chart-wrapper { height: 300px; } }
</style>

<div class="dashboard-shell">
    <div class="mb-4">
        <div class="dash-eyebrow mb-1">Dashboard Anggaran Seksi</div>
        <h2 class="fw-bold mb-1" style="font-size:1.5rem;color:#0f172a;letter-spacing:-.03em;">
            <?= htmlspecialchars($pageTitle) ?>
        </h2>
        <p class="text-muted mb-0" style="font-size:.9rem;">Tahun Anggaran <?= $chartYear ?> · Realisasi dihitung dari transaksi terverifikasi</p>
    </div>

    <section class="hero-card hero-accent mb-4">
        <div class="row g-0 align-items-center">
            <div class="col-lg-4 p-4 p-lg-5 d-flex justify-content-center">
                <div class="hero-ring-wrap">
                    <svg viewBox="0 0 120 120" width="100%" height="100%" role="img" aria-label="Serapan <?= number_format($percentage, 2) ?> persen">
                        <circle cx="60" cy="60" r="52" fill="none" stroke="#e0e7ff" stroke-width="12" />
                        <circle cx="60" cy="60" r="52" fill="none" stroke="#4f46e5" stroke-width="12" stroke-linecap="round"
                            stroke-dasharray="<?= $ringDash ?> 100" pathLength="100" transform="rotate(-90 60 60)" />
                    </svg>
                    <div class="hero-ring-text">
                        <div class="hero-ring-value"><?= number_format($percentage, 2) ?>%</div>
                        <div class="dash-eyebrow mt-2">Serapan</div>
                    </div>
                </div>
            </div>
            <div class="col-lg-8 p-4 p-lg-5 pt-lg-4">
                <div class="dash-eyebrow mb-2">Ringkasan Realisasi</div>
                <h3 class="fw-bold mb-2" style="font-size:1.55rem;color:#111827;letter-spacing:-.03em;">Realisasi Anggaran Terverifikasi</h3>
                <p class="text-muted mb-4" style="max-width:680px;font-size:.92rem;">Pantau posisi realisasi terhadap pagu dan rencana anggaran kas tanpa mengubah cakupan data per seksi.</p>
                <div class="row g-3">
                    <div class="col-md-6 col-xl-3">
                        <div class="support-card">
                            <div class="dash-eyebrow">Pagu</div>
                            <div class="support-value mt-2">Rp <?= number_format($stats['total_pagu'], 0, ',', '.') ?></div>
                        </div>
                    </div>
                    <div class="col-md-6 col-xl-3">
                        <div class="support-card">
                            <div class="dash-eyebrow">RAK</div>
                            <div class="support-value mt-2">Rp <?= number_format($stats['total_rak'], 0, ',', '.') ?></div>
                        </div>
                    </div>
                    <div class="col-md-6 col-xl-3">
                        <div class="support-card">
                            <div class="dash-eyebrow">Realisasi</div>
                            <div class="support-value mt-2">Rp <?= number_format($stats['total_realisasi'], 0, ',', '.') ?></div>
                        </div>
                    </div>
                    <div class="col-md-6 col-xl-3">
                        <div class="support-card">
                            <div class="dash-eyebrow">Sisa</div>
                            <div class="support-value mt-2" style="color:<?= $stats['sisa_anggaran'] < 0 ? '#dc2626' : '#0f172a' ?>;">Rp <?= number_format(abs($stats['sisa_anggaran']), 0, ',', '.') ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="chart-card p-4">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
            <div>
                <div class="dash-eyebrow mb-1">RAK vs Realisasi Bulanan</div>
                <h5 class="fw-bold mb-0" style="color:#0f172a;letter-spacing:-.02em;">Pergerakan Serapan Tahun <?= $chartYear ?></h5>
            </div>
            <div class="d-flex flex-wrap gap-3 text-muted" style="font-size:.8rem;">
                <span><i class="legend-dot" style="background:#4f46e5;"></i> RAK bulan berjalan/lewat</span>
                <span><i class="legend-dot" style="background:rgba(79,70,229,.28);"></i> RAK proyeksi</span>
                <span><i class="legend-dot" style="background:#10b981;"></i> Realisasi</span>
            </div>
        </div>
        <div class="chart-wrapper">
            <canvas id="monthlyChart"></canvas>
        </div>
    </section>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const monthlyData = <?= json_encode($monthlyData) ?>;
const bulanNames = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
const chartYear = <?= $chartYear ?>;
const currentYear = <?= $currentYear ?>;
const currentMonth = <?= $currentMonth ?>;

function isFutureMonth(index) {
    const month = index + 1;
    return chartYear > currentYear || (chartYear === currentYear && month > currentMonth);
}

const rakColors = bulanNames.map((_, i) => isFutureMonth(i) ? 'rgba(79,70,229,.28)' : 'rgba(79,70,229,.72)');
const rakBorderColors = bulanNames.map((_, i) => isFutureMonth(i) ? 'rgba(79,70,229,.40)' : 'rgba(79,70,229,1)');
const realisasiColors = bulanNames.map((_, i) => isFutureMonth(i) ? 'rgba(16,185,129,.18)' : 'rgba(16,185,129,.76)');

const currentMonthMarker = {
    id: 'currentMonthMarker',
    afterDatasetsDraw(chart) {
        if (chartYear !== currentYear) return;
        const {ctx, chartArea, scales} = chart;
        const x = scales.x.getPixelForValue(currentMonth - 1) + (scales.x.width / 12 / 2);
        ctx.save();
        ctx.beginPath();
        ctx.setLineDash([5, 5]);
        ctx.moveTo(x, chartArea.top);
        ctx.lineTo(x, chartArea.bottom);
        ctx.lineWidth = 1;
        ctx.strokeStyle = 'rgba(15,23,42,.28)';
        ctx.stroke();
        ctx.setLineDash([]);
        ctx.fillStyle = '#475569';
        ctx.font = '600 11px system-ui';
        ctx.fillText('bulan berjalan', Math.min(x + 8, chartArea.right - 90), chartArea.top + 14);
        ctx.restore();
    }
};

const ctx = document.getElementById('monthlyChart').getContext('2d');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: bulanNames,
        datasets: [
            {
                label: 'RAK',
                data: Object.values(monthlyData.rak),
                backgroundColor: rakColors,
                borderColor: rakBorderColors,
                borderWidth: 1.5,
                borderRadius: 6,
                maxBarThickness: 34
            },
            {
                label: 'Realisasi',
                data: Object.values(monthlyData.realisasi),
                backgroundColor: realisasiColors,
                borderColor: 'rgba(16,185,129,1)',
                borderWidth: 1.5,
                borderRadius: 6,
                maxBarThickness: 34
            }
        ]
    },
    plugins: [currentMonthMarker],
    options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    afterTitle(items) {
                        const idx = items[0].dataIndex;
                        return isFutureMonth(idx) ? 'Proyeksi RAK bulan mendatang' : '';
                    },
                    label(context) {
                        return context.dataset.label + ': Rp ' + context.parsed.y.toLocaleString('id-ID');
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                border: { display: false },
                grid: { color: '#eef2f7' },
                ticks: {
                    color: '#64748b',
                    font: { size: 11, weight: '600' },
                    callback(value) {
                        return 'Rp ' + (value >= 1000000 ? (value / 1000000).toLocaleString('id-ID') + ' Jt' : value.toLocaleString('id-ID'));
                    }
                }
            },
            x: {
                border: { display: false },
                grid: { display: false },
                ticks: { color: '#475569', font: { size: 12, weight: '700' } }
            }
        }
    }
});
</script>
