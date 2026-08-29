<style>
:root {
    --primary: #3b82f6;
    --gray-50: #f8fafc;
    --gray-100: #f1f5f9;
    --gray-200: #e2e8f0;
    --gray-400: #94a3b8;
    --gray-600: #475569;
    --gray-700: #334155;
    --gray-800: #1e293b;
    --gray-900: #0f172a;
}
.kpi-card {
    border: 1px solid var(--gray-200);
    border-radius: 14px;
    background: #ffffff;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
    transition: all 0.2s ease;
}
.kpi-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(0,0,0,0.06);
}
.kpi-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.4rem;
}
.kpi-label {
    font-size: 0.75rem;
    color: var(--gray-400);
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
}
.kpi-value {
    font-size: 1.35rem;
    font-weight: 800;
    color: var(--gray-900);
    margin-top: 0.2rem;
    letter-spacing: -0.02em;
}
.chart-wrapper {
    position: relative;
    height: 320px;
}
</style>

<div class="mb-4 d-flex flex-wrap justify-content-between align-items-center gap-3">
    <div>
        <h3 style="font-weight:800;color:var(--gray-900);letter-spacing:-0.02em;margin-bottom:0.25rem;">
            <i class="bi bi-building me-2 text-primary"></i><?= htmlspecialchars($pageTitle) ?>
        </h3>
        <p class="text-muted mb-0" style="font-size:0.875rem;">Monitoring Realisasi Anggaran Seksi Tahun <strong class="text-dark"><?= $stats['tahun'] ?></strong></p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="<?= base_url('seksi/transaksi/create') ?>" class="btn btn-primary btn-sm px-3 py-2 fw-semibold" style="border-radius:8px;">
            <i class="bi bi-plus-circle me-1"></i> Input Transaksi
        </a>
        <a href="<?= base_url('seksi/transaksi') ?>" class="btn btn-outline-secondary btn-sm px-3 py-2 fw-semibold" style="border-radius:8px;">
            <i class="bi bi-receipt me-1"></i> Transaksi Saya
        </a>
    </div>
</div>

<!-- KPI CARDS -->
<div class="row mb-4 g-3">
    <div class="col-xl-3 col-md-6">
        <div class="kpi-card h-100 p-3">
            <div class="d-flex align-items-start gap-3">
                <div class="kpi-icon" style="background:#eff6ff;color:#2563eb;">
                    <i class="bi bi-cash-stack"></i>
                </div>
                <div style="flex:1;">
                    <div class="kpi-label">Total Pagu</div>
                    <div class="kpi-value">Rp <?= number_format($stats['total_pagu'], 0, ',', '.') ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="kpi-card h-100 p-3">
            <div class="d-flex align-items-start gap-3">
                <div class="kpi-icon" style="background:#f0fdf4;color:#16a34a;">
                    <i class="bi bi-calendar-check"></i>
                </div>
                <div style="flex:1;">
                    <div class="kpi-label">Total RAK</div>
                    <div class="kpi-value">Rp <?= number_format($stats['total_rak'], 0, ',', '.') ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="kpi-card h-100 p-3">
            <div class="d-flex align-items-start gap-3">
                <div class="kpi-icon" style="background:#ecfdf5;color:#059669;">
                    <i class="bi bi-receipt-cutoff"></i>
                </div>
                <div style="flex:1;">
                    <div class="kpi-label">Realisasi (Terverifikasi)</div>
                    <div class="kpi-value">Rp <?= number_format($stats['total_realisasi'], 0, ',', '.') ?></div>
                    <div class="mt-1">
                        <span class="badge" style="font-size:0.75rem;font-weight:700;background:#dcfce7;color:#15803d;">
                            <?= number_format($stats['percentage'], 2) ?>% Serapan
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="kpi-card h-100 p-3">
            <div class="d-flex align-items-start gap-3">
                <div class="kpi-icon" style="background:#fffbeb;color:#d97706;">
                    <i class="bi bi-wallet2"></i>
                </div>
                <div style="flex:1;">
                    <div class="kpi-label">Sisa Anggaran</div>
                    <div class="kpi-value" style="color:<?= $stats['sisa_anggaran'] < 0 ? '#dc2626' : '#0f172a' ?>;">
                        Rp <?= number_format(abs($stats['sisa_anggaran']), 0, ',', '.') ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- GRAFIK -->
<div class="card border-0 shadow-sm" style="border-radius:14px;">
    <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="fw-bold mb-0 text-dark" style="font-size:1.05rem;">
                <i class="bi bi-graph-up text-primary me-2"></i>Grafik Realisasi Bulanan vs RAK
            </h5>
            <span class="text-muted" style="font-size:0.8rem;">Tahun Anggaran <?= $stats['tahun'] ?></span>
        </div>
        <div class="chart-wrapper">
            <canvas id="monthlyChart"></canvas>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const monthlyData = <?= json_encode($monthlyData) ?>;
const bulanNames = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];

const ctx = document.getElementById('monthlyChart').getContext('2d');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: bulanNames,
        datasets: [
            {
                label: 'RAK (Rencana)',
                data: Object.values(monthlyData.rak),
                backgroundColor: 'rgba(59, 130, 246, 0.45)',
                borderColor: 'rgba(59, 130, 246, 1)',
                borderWidth: 1.5,
                borderRadius: 4
            },
            {
                label: 'Realisasi (Terverifikasi)',
                data: Object.values(monthlyData.realisasi),
                backgroundColor: 'rgba(16, 185, 129, 0.65)',
                borderColor: 'rgba(16, 185, 129, 1)',
                borderWidth: 1.5,
                borderRadius: 4
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'top',
                labels: {
                    boxWidth: 14,
                    usePointStyle: true,
                    font: { size: 12, weight: '600' }
                }
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return context.dataset.label + ': Rp ' + context.parsed.y.toLocaleString('id-ID');
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                grid: { color: '#f1f5f9' },
                ticks: {
                    font: { size: 11 },
                    callback: function(value) {
                        return 'Rp ' + (value >= 1000000 ? (value / 1000000) + ' Jt' : value.toLocaleString('id-ID'));
                    }
                }
            },
            x: {
                grid: { display: false },
                ticks: { font: { size: 12, weight: '600' } }
            }
        }
    }
});
</script>
